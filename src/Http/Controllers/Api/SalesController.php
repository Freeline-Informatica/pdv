<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\ProductCatalogRepository;
use Freeline\Pdv\Contracts\StockMovementService;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\RestaurantFicha;
use Freeline\Pdv\Models\Sale;
use Freeline\Pdv\Models\SaleFiscalDocument;
use Freeline\Pdv\Models\SaleItem;
use Freeline\Pdv\Models\SalePayment;
use Freeline\Pdv\Services\NotaAgilConfigurationException;
use Freeline\Pdv\Services\NotaAgilFiscalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SalesController extends Controller
{
    private const CANCEL_WINDOWS = [
        'nfce' => 1800,
        'nfe' => 86400,
    ];

    public function __construct(
        private readonly ProductCatalogRepository $products,
        private readonly StockMovementService $stockMovements,
        private readonly CompanyContextResolver $companyContext,
        private readonly NotaAgilFiscalService $notaAgil,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedSaleQuery()
            ->with([
                'creator:id,name',
                'canceler:id,name',
                'fiscalDocument',
            ])
            ->withCount(['items', 'payments'])
            ->orderByDesc('sold_at')
            ->orderByDesc('created_at');

        if ($request->filled('status') && in_array($request->string('status')->toString(), ['finalizada', 'cancelada'], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('CAST(numero as CHAR) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(cliente_nome, \'\')) like ?', ["%{$needle}%"]);
            });
        }

        $now = now();
        $sales = $query->get();

        return response()->json(
            $sales
                ->map(fn (Sale $sale) => $this->presentSummary($sale, $now))
                ->values(),
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->ensureSaleBelongsToCurrentScope($sale);

        $sale->load([
            'items.product',
            'items.catalogProduct',
            'payments',
            'creator:id,name',
            'canceler:id,name',
            'fiscalDocument',
        ]);

        return response()->json($this->presentDetail($sale, now()));
    }

    public function cancel(Request $request, Sale $sale): JsonResponse
    {
        $payload = $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        $operatorId = $request->user()?->id;
        $sale->loadMissing('fiscalDocument');
        $fiscalDocument = $sale->fiscalDocument;

        if ($fiscalDocument?->status === SaleFiscalDocument::STATUS_AUTHORIZED) {
            try {
                $this->notaAgil->cancel($fiscalDocument, trim($payload['motivo']), FiscalConfig::query()->first());
            } catch (Throwable $error) {
                throw ValidationException::withMessages([
                    'fiscal' => ['Não foi possível cancelar o documento fiscal no NotaAgil: '.$this->notaAgil->exceptionMessage($error)],
                ]);
            }
        }

        DB::transaction(function () use ($sale, $payload, $operatorId): void {
            $record = $this->scopedSaleQuery()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($record->status === Sale::STATUS_CANCELED) {
                throw ValidationException::withMessages([
                    'status' => ['Esta venda já está cancelada.'],
                ]);
            }

            if ($record->status !== Sale::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'status' => ['Somente vendas finalizadas podem ser canceladas.'],
                ]);
            }

            $cancelWindow = $this->resolveCancelWindow($record, now());
            if (! $cancelWindow['can_cancel']) {
                throw ValidationException::withMessages([
                    'status' => ['Prazo de cancelamento expirado para '.($cancelWindow['document_label'] ?? 'a nota').'.'],
                ]);
            }

            foreach ($record->items as $item) {
                $productId = $item->product_id ?: ($item->catalog_product_id ?? null);
                if (! $productId) continue;

                $this->stockMovements->increase($productId, (float) $item->quantidade, [
                    'origem' => 'cancelamento_venda',
                    'origem_id' => $record->id,
                    'referencia' => 'Cancelamento Venda #'.$record->numero,
                    'descricao' => $payload['motivo'],
                    'happened_at' => now(),
                    'user_id' => $operatorId,
                ]);
            }

            $record->status = Sale::STATUS_CANCELED;
            $record->canceled_at = now();
            $record->canceled_by = $operatorId;
            $record->cancellation_reason = trim($payload['motivo']);
            $record->save();

            $record->fiscalDocument()
                ->where('status', '!=', SaleFiscalDocument::STATUS_CANCELLED)
                ->update([
                    'status' => SaleFiscalDocument::STATUS_CANCELLED,
                    'updated_at' => now(),
                ]);
        });

        $sale->load([
            'items.product',
            'items.catalogProduct',
            'payments',
            'creator:id,name',
            'canceler:id,name',
            'fiscalDocument',
        ]);

        return response()->json($this->presentDetail($sale->fresh([
            'items.product',
            'items.catalogProduct',
            'payments',
            'creator:id,name',
            'canceler:id,name',
            'fiscalDocument',
        ]), now()));
    }

    public function finalize(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'customer' => ['nullable', 'array'],
            'customer.nome' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.nome' => ['required', 'string', 'max:255'],
            'items.*.codigo' => ['nullable', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'items.*.valor_unitario' => ['required', 'numeric', 'gte:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method_name' => ['required', 'string', 'max:255'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.installments' => ['nullable', 'integer', 'min:1', 'max:36'],
            'payments.*.interest_amount' => ['nullable', 'numeric', 'gte:0'],
            'complementary' => ['nullable', 'array'],
            'complementary.document_model' => ['nullable', 'string', 'max:20'],
            'complementary.document_series' => ['nullable', 'string', 'max:20'],
            'complementary.restaurant_ficha_id' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_table_id' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_ficha_code' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_table_code' => ['nullable', 'string', 'max:80'],
            'totals' => ['nullable', 'array'],
        ]);

        $operatorId = $request->user()?->id;
        $now = now();
        $scope = $this->currentScope();
        $previewTechnicalError = null;

        $result = DB::transaction(function () use ($payload, $operatorId, $now, $scope, &$previewTechnicalError): array {
            $documentType = $this->normalizeDocumentType(
                (string) data_get($payload, 'complementary.document_model', 'NFC-e'),
            );

            $fiscalConfig = config('pdv.mode') === 'standalone'
                ? FiscalConfig::query()->lockForUpdate()->first()
                : null;
            [$saleNumber, $saleSeries, $nextFiscalNumber] = $this->resolveSaleNumberAndSeries(
                $documentType,
                $fiscalConfig,
                (string) data_get($payload, 'complementary.document_series', '1'),
            );

            if (($fiscalConfig?->notagil_enabled ?? false) && ! $this->notaAgil->isEnabled($fiscalConfig)) {
                throw ValidationException::withMessages([
                    'fiscal' => ['Configure NOTAGIL_TOKEN para emitir documentos pelo NotaAgilApi.'],
                ]);
            }

            $notaAgilEnabled = $this->notaAgil->isEnabled($fiscalConfig);
            $operationCode = null;
            if ($notaAgilEnabled && $fiscalConfig) {
                try {
                    $operationCode = $this->notaAgil->operationCode($fiscalConfig, $documentType);
                    $this->notaAgil->previewCheckout($payload, $fiscalConfig, $documentType, $saleSeries, $saleNumber, $now, $operatorId);
                } catch (NotaAgilConfigurationException $error) {
                    throw ValidationException::withMessages([
                        'fiscal' => [$error->getMessage()],
                    ]);
                } catch (Throwable $error) {
                    if ($this->notaAgil->isFiscalValidationError($error)) {
                        throw ValidationException::withMessages([
                            'fiscal' => [$this->notaAgil->exceptionMessage($error)],
                        ]);
                    }

                    $previewTechnicalError = $error;
                }
            }

            $itemsPayload = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $paymentsPayload = is_array($payload['payments'] ?? null) ? $payload['payments'] : [];
            $productsTotal = 0.0;
            $interestTotal = 0.0;
            $paidTotal = 0.0;

            foreach ($itemsPayload as $item) {
                $quantity = round((float) ($item['quantidade'] ?? 0), 3);
                $unitPrice = round((float) ($item['valor_unitario'] ?? 0), 2);
                $productsTotal = round($productsTotal + round($quantity * $unitPrice, 2), 2);
            }

            foreach ($paymentsPayload as $payment) {
                $amount = round((float) ($payment['amount'] ?? 0), 2);
                $interest = round((float) ($payment['interest_amount'] ?? 0), 2);

                $paidTotal = round($paidTotal + $amount, 2);
                $interestTotal = round($interestTotal + $interest, 2);
            }

            $totalFinanceiro = round($productsTotal + $interestTotal, 2);
            if ($paidTotal + 0.009 < $totalFinanceiro) {
                throw ValidationException::withMessages([
                    'payments' => ['Total recebido é menor que o total da venda.'],
                ]);
            }

            $sale = Sale::query()->create([
                'grupo_empresarial_id' => $scope['grupo_id'],
                'estabelecimento_id' => $scope['estabelecimento_id'],
                'numero' => $saleNumber,
                'status' => Sale::STATUS_FINALIZED,
                'document_type' => $documentType,
                'cliente_nome' => trim((string) data_get($payload, 'customer.nome', '')) ?: null,
                'total_bruto' => $productsTotal,
                'total_financeiro' => $totalFinanceiro,
                'juros_total' => $interestTotal,
                'sold_at' => $now,
                'created_by' => $operatorId,
            ]);

            foreach ($itemsPayload as $item) {
                $quantity = round((float) ($item['quantidade'] ?? 0), 3);
                $unitPrice = round((float) ($item['valor_unitario'] ?? 0), 2);
                $lineTotal = round($quantity * $unitPrice, 2);

                $productId = null;
                $catalogProductId = null;
                $productName = trim((string) ($item['nome'] ?? 'Produto'));
                $productCode = trim((string) ($item['codigo'] ?? '')) ?: null;
                $unit = 'UN';

                $incomingProductId = $item['id'] ?? null;
                $catalogProduct = $incomingProductId ? $this->products->find($incomingProductId) : null;

                if ($catalogProduct) {
                    if (config('pdv.mode') === 'erp') {
                        $productId = $catalogProduct['id'];
                    } else {
                        $catalogProductId = $catalogProduct['id'];
                    }

                    $productName = $productName ?: (string) ($catalogProduct['nome'] ?? 'Produto');
                    $productCode = $productCode ?: ($catalogProduct['codigo'] ?? null);
                    $unit = strtoupper((string) ($catalogProduct['unidade'] ?? 'UN'));

                    $this->stockMovements->decrease($catalogProduct['id'], $quantity, [
                        'origem' => 'pdv_venda',
                        'origem_id' => $sale->id,
                        'referencia' => 'Venda #'.$sale->numero,
                        'descricao' => 'Baixa automática da venda',
                        'happened_at' => $now,
                        'user_id' => $operatorId,
                    ]);
                }

                $saleItemPayload = [
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'produto_nome' => $productName ?: 'Produto',
                    'produto_codigo' => $productCode,
                    'quantidade' => $quantity,
                    'unidade' => $unit,
                    'valor_unitario' => $unitPrice,
                    'valor_total' => $lineTotal,
                ];

                if (config('pdv.mode') !== 'erp') {
                    $saleItemPayload['catalog_product_id'] = $catalogProductId;
                }

                SaleItem::query()->create($saleItemPayload);
            }

            foreach ($paymentsPayload as $payment) {
                $amount = round((float) ($payment['amount'] ?? 0), 2);
                $methodName = trim((string) ($payment['method_name'] ?? 'Pagamento'));

                SalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'metodo_nome' => $methodName ?: 'Pagamento',
                    'descricao' => $this->buildPaymentDescription($payment),
                    'valor' => $amount,
                ]);
            }

            if ($fiscalConfig && $nextFiscalNumber > 0) {
                if ($documentType === 'nfe') {
                    $fiscalConfig->proximo_numero_nfe = (string) $nextFiscalNumber;
                } else {
                    $fiscalConfig->proximo_numero_nfce = (string) $nextFiscalNumber;
                }
                $fiscalConfig->save();
            }

            $this->markRestaurantFichaAsPaid($payload, $now);

            $fiscalDocument = null;
            if ($notaAgilEnabled && $fiscalConfig && $operationCode) {
                $fiscalDocument = $this->notaAgil->makeFiscalDocument($sale, $fiscalConfig, $saleSeries, $operationCode);
                $fiscalDocument->save();

                if ($previewTechnicalError) {
                    $this->notaAgil->markContingency($fiscalDocument, $previewTechnicalError);
                }
            }

            return [
                'sale' => $sale,
                'series' => $saleSeries,
                'fiscal_document' => $fiscalDocument,
            ];
        });

        /** @var Sale $sale */
        $sale = $result['sale'];
        $series = (string) ($result['series'] ?? '1');
        $fiscalDocument = $result['fiscal_document'] ?? null;

        if ($fiscalDocument instanceof SaleFiscalDocument && ! $previewTechnicalError) {
            try {
                $fiscalDocument = $this->notaAgil->submitAndWait($fiscalDocument, FiscalConfig::query()->first());
            } catch (Throwable $error) {
                $fiscalDocument = $this->notaAgil->isFiscalValidationError($error)
                    ? $this->notaAgil->markRejected($fiscalDocument, $error)
                    : $this->notaAgil->markContingency($fiscalDocument, $error);
            }
        }

        return response()->json([
            'id' => $sale->id,
            'numero' => (int) $sale->numero,
            'serie' => $series,
            'status' => $this->resolveEmissionStatusLabel($fiscalDocument),
            'document_type' => $sale->document_type,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'total_financeiro' => $sale->total_financeiro,
            'fiscal' => $this->presentFiscalDocument($fiscalDocument),
        ]);
    }

    public function retryFiscal(Sale $sale): JsonResponse
    {
        $this->ensureSaleBelongsToCurrentScope($sale);
        $sale->loadMissing('fiscalDocument');
        $document = $sale->fiscalDocument;

        if (! $document) {
            throw ValidationException::withMessages([
                'fiscal' => ['Esta venda não possui documento fiscal vinculado.'],
            ]);
        }

        if ($document->status === SaleFiscalDocument::STATUS_AUTHORIZED) {
            return response()->json([
                'message' => 'Documento fiscal já autorizado.',
                'fiscal' => $this->presentFiscalDocument($document),
            ]);
        }

        try {
            $document = $this->notaAgil->submitAndWait($document, FiscalConfig::query()->first());
        } catch (Throwable $error) {
            $document = $this->notaAgil->isFiscalValidationError($error)
                ? $this->notaAgil->markRejected($document, $error)
                : $this->notaAgil->markContingency($document, $error);
        }

        return response()->json([
            'message' => $this->resolveEmissionStatusLabel($document),
            'fiscal' => $this->presentFiscalDocument($document),
        ]);
    }

    public function syncFiscal(Sale $sale): JsonResponse
    {
        $this->ensureSaleBelongsToCurrentScope($sale);
        $sale->loadMissing('fiscalDocument');
        $document = $sale->fiscalDocument;

        if (! $document) {
            throw ValidationException::withMessages([
                'fiscal' => ['Esta venda não possui documento fiscal vinculado.'],
            ]);
        }

        try {
            $document = $this->notaAgil->sync($document, FiscalConfig::query()->first());
        } catch (Throwable $error) {
            if ($this->notaAgil->isFiscalValidationError($error)) {
                $document = $this->notaAgil->markRejected($document, $error);
            } else {
                throw ValidationException::withMessages([
                    'fiscal' => ['Não foi possível sincronizar com NotaAgil: '.$this->notaAgil->exceptionMessage($error)],
                ]);
            }
        }

        return response()->json([
            'message' => $this->resolveEmissionStatusLabel($document),
            'fiscal' => $this->presentFiscalDocument($document),
        ]);
    }

    public function fiscalArtifact(Sale $sale, string $artifact): Response|JsonResponse
    {
        if (! in_array($artifact, ['xml', 'pdf'], true)) {
            abort(404);
        }

        $this->ensureSaleBelongsToCurrentScope($sale);
        $sale->loadMissing('fiscalDocument');
        $document = $sale->fiscalDocument;

        if (! $document || $document->status !== SaleFiscalDocument::STATUS_AUTHORIZED) {
            throw ValidationException::withMessages([
                'fiscal' => ['O documento fiscal ainda não está autorizado para download.'],
            ]);
        }

        $payload = $this->notaAgil->download($document, $artifact, FiscalConfig::query()->first());
        $content = (string) (data_get($payload, 'content') ?: data_get($payload, 'base64') ?: data_get($payload, 'raw') ?: '');
        $binary = (bool) data_get($payload, 'base64', false) ? base64_decode($content) : $content;

        return response($binary, 200, [
            'Content-Type' => $artifact === 'xml' ? 'application/xml' : 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="sale-%s.%s"', $sale->numero, $artifact),
        ]);
    }

    private function markRestaurantFichaAsPaid(array $payload, Carbon $now): void
    {
        $fichaId = trim((string) data_get($payload, 'complementary.restaurant_ficha_id', ''));
        if ($fichaId === '') {
            return;
        }

        $ficha = RestaurantFicha::query()
            ->lockForUpdate()
            ->find($fichaId);

        if (! $ficha) {
            return;
        }

        $expectedTableId = trim((string) data_get($payload, 'complementary.restaurant_table_id', ''));
        if ($expectedTableId !== '' && (string) $ficha->table_id !== $expectedTableId) {
            throw ValidationException::withMessages([
                'complementary.restaurant_table_id' => ['A ficha informada não corresponde à mesa selecionada.'],
            ]);
        }

        if (in_array($ficha->status, [RestaurantFicha::STATUS_CANCELED, RestaurantFicha::STATUS_PAID], true)) {
            return;
        }

        $ficha->status = RestaurantFicha::STATUS_PAID;
        $ficha->closed_at = $ficha->closed_at ?: $now;
        $ficha->save();
    }

    private function normalizeDocumentType(string $documentModel): string
    {
        $normalized = mb_strtolower(preg_replace('/[^a-z]/', '', $documentModel));
        if ($normalized === 'nfe') return 'nfe';
        return 'nfce';
    }

    private function resolveSaleNumberAndSeries(string $documentType, ?FiscalConfig $fiscalConfig, string $payloadSeries): array
    {
        $lastSaleNumber = Sale::query()
            ->select('numero')
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->first();
        $maxNumber = (int) ($lastSaleNumber?->numero ?? 0);

        $configuredNumberRaw = $documentType === 'nfe'
            ? (string) ($fiscalConfig?->proximo_numero_nfe ?? '')
            : (string) ($fiscalConfig?->proximo_numero_nfce ?? '');

        $configuredNumber = (int) preg_replace('/\D+/', '', $configuredNumberRaw);
        $candidateNumber = max(1, $maxNumber + 1, $configuredNumber > 0 ? $configuredNumber : 1);
        $nextFiscalNumber = $candidateNumber + 1;

        $series = trim($payloadSeries);
        if ($series === '') {
            $series = $documentType === 'nfe'
                ? trim((string) ($fiscalConfig?->serie_nfe ?? ''))
                : trim((string) ($fiscalConfig?->serie_nfce ?? ''));
        }
        if ($series === '') {
            $series = '1';
        }

        return [$candidateNumber, $series, $nextFiscalNumber];
    }

    private function buildPaymentDescription(array $payment): ?string
    {
        $parts = [];
        $installments = max(1, (int) ($payment['installments'] ?? 1));
        $interestAmount = round((float) ($payment['interest_amount'] ?? 0), 2);

        if ($installments > 1) {
            $parts[] = $installments.'x';
        }

        if ($interestAmount > 0) {
            $parts[] = 'juros '.number_format($interestAmount, 2, ',', '.');
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    private function resolveCancelWindow(Sale $sale, Carbon $now): array
    {
        $documentType = mb_strtolower((string) $sale->document_type);
        $windowSeconds = self::CANCEL_WINDOWS[$documentType] ?? 0;
        $soldAt = $sale->sold_at ?? $sale->created_at ?? $now;
        $deadlineAt = $soldAt->copy()->addSeconds($windowSeconds);
        $remainingSeconds = max(0, $deadlineAt->getTimestamp() - $now->getTimestamp());

        return [
            'document_type' => $documentType,
            'document_label' => $this->documentLabel($documentType),
            'window_seconds' => $windowSeconds,
            'deadline_at' => $deadlineAt->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
            'can_cancel' => $sale->status === Sale::STATUS_FINALIZED && $windowSeconds > 0 && $remainingSeconds > 0,
        ];
    }

    private function presentSummary(Sale $sale, Carbon $now): array
    {
        return [
            'id' => $sale->id,
            'grupo_empresarial_id' => $sale->grupo_empresarial_id,
            'estabelecimento_id' => $sale->estabelecimento_id,
            'numero' => (int) $sale->numero,
            'status' => $sale->status,
            'status_label' => $sale->status === Sale::STATUS_CANCELED ? 'Cancelada' : 'Finalizada',
            'document_type' => $sale->document_type,
            'document_label' => $this->documentLabel((string) $sale->document_type),
            'cliente_nome' => $sale->cliente_nome,
            'total_bruto' => $sale->total_bruto,
            'total_financeiro' => $sale->total_financeiro,
            'juros_total' => $sale->juros_total,
            'sold_at' => $sale->sold_at,
            'canceled_at' => $sale->canceled_at,
            'created_at' => $sale->created_at,
            'items_count' => (int) ($sale->items_count ?? 0),
            'payments_count' => (int) ($sale->payments_count ?? 0),
            'creator' => $sale->creator ? [
                'id' => $sale->creator->id,
                'name' => $sale->creator->name,
            ] : null,
            'canceler' => $sale->canceler ? [
                'id' => $sale->canceler->id,
                'name' => $sale->canceler->name,
            ] : null,
            'fiscal' => $this->presentFiscalDocument($sale->fiscalDocument),
            'cancel_policy' => $this->resolveCancelWindow($sale, $now),
        ];
    }

    private function presentFiscalDocument(?SaleFiscalDocument $document): ?array
    {
        if (! $document) {
            return null;
        }

        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'environment' => $document->environment,
            'serie' => $document->series,
            'series' => $document->series,
            'numero' => $document->number,
            'number' => $document->number,
            'operation_code' => $document->operation_code,
            'external_id' => $document->external_id,
            'status' => $document->status,
            'status_label' => $this->resolveEmissionStatusLabel($document),
            'fiscal_status' => $document->fiscal_status,
            'operational_status' => $document->operational_status,
            'chave_acesso' => $document->access_key,
            'access_key' => $document->access_key,
            'protocolo' => $document->protocol,
            'protocol' => $document->protocol,
            'autorizado_em' => $document->authorized_at?->toIso8601String(),
            'authorized_at' => $document->authorized_at?->toIso8601String(),
            'last_error' => $document->last_error,
            'attempts' => (int) $document->attempts,
            'next_retry_at' => $document->next_retry_at?->toIso8601String(),
            'contingency_printed_at' => $document->contingency_printed_at?->toIso8601String(),
            'xml_url' => $document->status === SaleFiscalDocument::STATUS_AUTHORIZED ? url("/api/pdv/sales/{$document->sale_id}/fiscal/xml") : null,
            'pdf_url' => $document->status === SaleFiscalDocument::STATUS_AUTHORIZED ? url("/api/pdv/sales/{$document->sale_id}/fiscal/pdf") : null,
        ];
    }

    private function resolveEmissionStatusLabel(?SaleFiscalDocument $document): string
    {
        return match ($document?->status) {
            SaleFiscalDocument::STATUS_AUTHORIZED => 'Autorizada',
            SaleFiscalDocument::STATUS_PROCESSING, SaleFiscalDocument::STATUS_PENDING => 'Processando emissão',
            SaleFiscalDocument::STATUS_CONTINGENCY_PENDING => 'Em contingência',
            SaleFiscalDocument::STATUS_REJECTED => 'Rejeitada',
            SaleFiscalDocument::STATUS_CANCELLED => 'Cancelada',
            default => 'Autorizada local',
        };
    }

    private function presentDetail(Sale $sale, Carbon $now): array
    {
        return [
            ...$this->presentSummary($sale, $now),
            'cancellation_reason' => $sale->cancellation_reason,
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'sale_id' => $item->sale_id,
                'product_id' => $item->product_id,
                'catalog_product_id' => $item->catalog_product_id,
                'produto_nome' => $item->produto_nome,
                'produto_codigo' => $item->produto_codigo,
                'quantidade' => $item->quantidade,
                'unidade' => $item->unidade,
                'valor_unitario' => $item->valor_unitario,
                'valor_total' => $item->valor_total,
            ])->values(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'sale_id' => $payment->sale_id,
                'metodo_nome' => $payment->metodo_nome,
                'descricao' => $payment->descricao,
                'valor' => $payment->valor,
            ])->values(),
        ];
    }

    private function documentLabel(string $documentType): string
    {
        $normalized = mb_strtolower($documentType);
        if ($normalized === 'nfce') return 'NFC-e';
        if ($normalized === 'nfe') return 'NF-e';
        return mb_strtoupper($documentType);
    }

    private function ensureSaleBelongsToCurrentScope(Sale $sale): void
    {
        if (config('pdv.mode') !== 'erp') {
            return;
        }

        $scope = $this->currentScope();
        if ((string) $sale->estabelecimento_id === (string) $scope['estabelecimento_id']) {
            return;
        }

        abort(404);
    }

    private function scopedSaleQuery(): Builder
    {
        $query = Sale::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $scope = $this->currentScope();

        return $query
            ->where('grupo_empresarial_id', $scope['grupo_id'])
            ->where('estabelecimento_id', $scope['estabelecimento_id']);
    }

    private function currentScope(): array
    {
        if (config('pdv.mode') !== 'erp') {
            return [
                'grupo_id' => null,
                'estabelecimento_id' => null,
            ];
        }

        $groupId = $this->companyContext->currentGroupId();
        $establishmentId = $this->companyContext->currentEstablishmentId();

        if (! $groupId || ! $establishmentId) {
            abort(409, 'Selecione uma filial no ERP para usar o PDV.');
        }

        return [
            'grupo_id' => (string) $groupId,
            'estabelecimento_id' => (string) $establishmentId,
        ];
    }
}
