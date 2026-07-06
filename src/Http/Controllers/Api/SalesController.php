<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\ProductCatalogRepository;
use Freeline\Pdv\Contracts\StockMovementService;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\PafDailyPaymentTotal;
use Freeline\Pdv\Models\PafDav;
use Freeline\Pdv\Models\PafExternalRequisition;
use Freeline\Pdv\Models\PafPreSale;
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
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    ) {}

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
                ->with(['items', 'payments'])
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
                if (! $productId) {
                    continue;
                }

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

            $fiscalConfig = config('pdv.mode') === 'standalone'
                ? FiscalConfig::query()->first()
                : null;
            if ($this->isPafEnabled($fiscalConfig)) {
                foreach ($record->payments as $payment) {
                    $this->recordPafDailyPaymentTotal(
                        $record,
                        (string) ($payment->metodo_nome ?: 'Pagamento'),
                        round((float) $payment->valor * -1, 2),
                        $payment->paf_document_type_code ?: $this->pafDocumentTypeCode((string) $record->document_type),
                    );
                }
            }

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
            'customer.id' => ['nullable', 'string', 'max:80'],
            'customer.nome' => ['nullable', 'string', 'max:255'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.cpf_cnpj' => ['nullable', 'string', 'max:30'],
            'customer.cpfCnpj' => ['nullable', 'string', 'max:30'],
            'customer.documento' => ['nullable', 'string', 'max:30'],
            'customer.document' => ['nullable', 'string', 'max:30'],
            'customer.cpf' => ['nullable', 'string', 'max:30'],
            'customer.cnpj' => ['nullable', 'string', 'max:30'],
            'customer.telefone' => ['nullable', 'string', 'max:30'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.tipo_pessoa' => ['nullable', 'string', 'max:30'],
            'customer.personType' => ['nullable', 'string', 'max:30'],
            'customer.cep' => ['nullable', 'string', 'max:20'],
            'customer.logradouro' => ['nullable', 'string', 'max:255'],
            'customer.street' => ['nullable', 'string', 'max:255'],
            'customer.numero' => ['nullable', 'string', 'max:30'],
            'customer.number' => ['nullable', 'string', 'max:30'],
            'customer.bairro' => ['nullable', 'string', 'max:120'],
            'customer.neighborhood' => ['nullable', 'string', 'max:120'],
            'customer.complemento' => ['nullable', 'string', 'max:120'],
            'customer.complement' => ['nullable', 'string', 'max:120'],
            'customer.cidade' => ['nullable', 'string', 'max:120'],
            'customer.city' => ['nullable', 'string', 'max:120'],
            'customer.uf' => ['nullable', 'string', 'max:2'],
            'customer.state' => ['nullable', 'string', 'max:2'],
            'customer.codigo_ibge' => ['nullable', 'string', 'max:20'],
            'customer.inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'customer.stateRegistration' => ['nullable', 'string', 'max:30'],
            'customer.indicador_ie' => ['nullable', 'string', 'max:2'],
            'customer.pais' => ['nullable', 'string', 'max:80'],
            'customer.country' => ['nullable', 'string', 'max:80'],
            'customer.country_code' => ['nullable', 'string', 'max:10'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.nome' => ['required', 'string', 'max:255'],
            'items.*.codigo' => ['nullable', 'string', 'max:255'],
            'items.*.codigo_barras' => ['nullable', 'string', 'max:255'],
            'items.*.unidade' => ['nullable', 'string', 'max:20'],
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
            'complementary.fiscal_observation' => ['nullable', 'string', 'max:500'],
            'complementary.observacao_nota' => ['nullable', 'string', 'max:500'],
            'complementary.restaurant_ficha_id' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_table_id' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_ficha_code' => ['nullable', 'string', 'max:80'],
            'complementary.restaurant_table_code' => ['nullable', 'string', 'max:80'],
            'complementary.paf_dav_id' => ['nullable', 'uuid'],
            'complementary.paf_pre_sale_id' => ['nullable', 'uuid'],
            'complementary.paf_external_requisition_id' => ['nullable', 'uuid'],
            'totals' => ['nullable', 'array'],
        ]);

        $operatorId = $request->user()?->id;
        $now = now();
        $scope = $this->currentScope();

        $result = DB::transaction(function () use ($payload, $operatorId, $now, $scope): array {
            $documentType = $this->normalizeDocumentType(
                (string) data_get($payload, 'complementary.document_model', 'NFC-e'),
            );

            $fiscalConfig = config('pdv.mode') === 'standalone'
                ? FiscalConfig::query()->lockForUpdate()->first()
                : null;
            $pafEnabled = $this->isPafEnabled($fiscalConfig);
            $pafReferences = $this->resolvePafReferences($payload, $scope);
            $fiscalObservation = $this->buildPafFiscalObservation($payload, $pafReferences);
            [$saleNumber, $saleSeries, $nextFiscalNumber] = $this->resolveSaleNumberAndSeries(
                $documentType,
                $fiscalConfig,
                (string) data_get($payload, 'complementary.document_series', '1'),
            );

            if (($fiscalConfig?->notagil_enabled ?? false) && ! $this->notaAgil->isEnabled($fiscalConfig)) {
                throw ValidationException::withMessages([
                    'fiscal' => ['Configure o token NotaAgil nas configurações fiscais para emitir documentos pelo NotaAgilApi.'],
                ]);
            }

            $notaAgilEnabled = $this->notaAgil->isEnabled($fiscalConfig);
            $operationCode = null;
            if ($notaAgilEnabled && $fiscalConfig) {
                $operationCode = $this->notaAgil->operationCode($fiscalConfig, $documentType);
                if (! $operationCode) {
                    $label = $documentType === 'nfe' ? 'NF-e' : 'NFC-e';
                    throw ValidationException::withMessages([
                        'fiscal' => ["Operation code NotaAgil ausente para {$label}. Configure o código técnico da operação fiscal antes de emitir por operação v2."],
                    ]);
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

            $customerSnapshot = $this->normalizeCustomerSnapshot($payload['customer'] ?? null);
            if ($documentType === 'nfe' && ! $this->hasIdentifiedCustomer($customerSnapshot)) {
                throw ValidationException::withMessages([
                    'customer' => ['Selecione um cliente com CPF/CNPJ para emitir NF-e.'],
                ]);
            }

            $sale = Sale::query()->create([
                'grupo_empresarial_id' => $scope['grupo_id'],
                'estabelecimento_id' => $scope['estabelecimento_id'],
                'numero' => $saleNumber,
                'status' => Sale::STATUS_FINALIZED,
                'document_type' => $documentType,
                'cliente_nome' => trim((string) data_get($customerSnapshot, 'nome', '')) ?: null,
                'customer_snapshot' => $customerSnapshot ?: null,
                'total_bruto' => $productsTotal,
                'total_financeiro' => $totalFinanceiro,
                'juros_total' => $interestTotal,
                'paf_dav_id' => $pafReferences['dav']?->id,
                'paf_pre_sale_id' => $pafReferences['pre_sale']?->id,
                'paf_external_requisition_id' => $pafReferences['external_requisition']?->id,
                'fiscal_observation' => $fiscalObservation,
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
                $productCode = trim((string) ($item['codigo_barras'] ?? ''))
                    ?: (trim((string) ($item['codigo'] ?? '')) ?: null);
                $unit = $this->normalizeSaleItemUnit($item['unidade'] ?? $item['unit'] ?? null);

                $incomingProductId = $item['id'] ?? null;
                $catalogProduct = $incomingProductId ? $this->products->find($incomingProductId) : null;

                if ($catalogProduct) {
                    if (config('pdv.mode') === 'erp') {
                        $productId = $catalogProduct['id'];
                    } else {
                        $catalogProductId = $catalogProduct['id'];
                    }

                    $productName = $productName ?: (string) ($catalogProduct['nome'] ?? 'Produto');
                    $productCode = $productCode ?: ($catalogProduct['codigo_barras'] ?? $catalogProduct['ean'] ?? $catalogProduct['gtin'] ?? $catalogProduct['codigo'] ?? null);
                    $unit = $this->normalizeSaleItemUnit(
                        data_get($catalogProduct, 'tributacao.unidade_tributavel')
                            ?: ($catalogProduct['unidade'] ?? null)
                            ?: $unit,
                    );

                    $this->stockMovements->decrease($catalogProduct['id'], $quantity, [
                        'origem' => 'pdv_venda',
                        'origem_id' => $sale->id,
                        'referencia' => 'Venda #'.$sale->numero,
                        'descricao' => 'Baixa automática da venda',
                        'happened_at' => $now,
                        'user_id' => $operatorId,
                    ]);
                }

                if ($pafEnabled) {
                    $this->assertPafSaleItemIsRegistered(
                        $item,
                        $catalogProduct,
                        $productName,
                        $productCode,
                        $unit,
                    );
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

            $pafDocumentTypeCode = $this->pafDocumentTypeCode($documentType);
            foreach ($paymentsPayload as $payment) {
                $amount = round((float) ($payment['amount'] ?? 0), 2);
                $methodName = trim((string) ($payment['method_name'] ?? 'Pagamento'));

                SalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'metodo_nome' => $methodName ?: 'Pagamento',
                    'descricao' => $this->buildPaymentDescription($payment),
                    'valor' => $amount,
                    'paf_document_type_code' => $pafDocumentTypeCode,
                ]);

                if ($pafEnabled) {
                    $this->recordPafDailyPaymentTotal($sale, $methodName ?: 'Pagamento', $amount, $pafDocumentTypeCode);
                }
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
            $this->markPafReferencesAsConverted($pafReferences, $sale, $now);

            $fiscalDocument = null;
            if ($notaAgilEnabled && $fiscalConfig && $operationCode) {
                try {
                    $fiscalDocument = $this->notaAgil->makeFiscalDocument($sale, $fiscalConfig, $saleSeries, $operationCode);
                } catch (NotaAgilConfigurationException $error) {
                    throw ValidationException::withMessages([
                        'fiscal' => [$error->getMessage()],
                    ]);
                }
                $fiscalDocument->save();
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

        if ($fiscalDocument instanceof SaleFiscalDocument) {
            try {
                $fiscalDocument = $this->notaAgil->submit($fiscalDocument, FiscalConfig::query()->first());
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
            $document = $this->notaAgil->syncArtifacts($document, FiscalConfig::query()->first());

            return response()->json([
                'message' => 'Documento fiscal já autorizado.',
                'fiscal' => $this->presentFiscalDocument($document, true),
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
            'fiscal' => $this->presentFiscalDocument($document, true),
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

        if ($document->status === SaleFiscalDocument::STATUS_AUTHORIZED) {
            $document = $this->notaAgil->syncArtifacts($document, FiscalConfig::query()->first());

            return response()->json([
                'message' => 'Documento fiscal já autorizado.',
                'fiscal' => $this->presentFiscalDocument($document, true),
            ]);
        }

        try {
            $document = $this->notaAgil->sync($document, FiscalConfig::query()->first());
            if ($document->status === SaleFiscalDocument::STATUS_AUTHORIZED) {
                $document = $this->notaAgil->syncArtifacts($document, FiscalConfig::query()->first());
            }
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
            'fiscal' => $this->presentFiscalDocument($document, true),
        ]);
    }

    public function fiscalEvents(Sale $sale): StreamedResponse|JsonResponse
    {
        $this->ensureSaleBelongsToCurrentScope($sale);
        $sale->loadMissing('fiscalDocument');
        $document = $sale->fiscalDocument;

        if (! $document) {
            throw ValidationException::withMessages([
                'fiscal' => ['Esta venda não possui documento fiscal vinculado.'],
            ]);
        }

        return response()->stream(function () use ($document): void {
            @set_time_limit(0);

            $deadline = time() + 60;
            $lastVersion = null;
            $lastHeartbeatAt = 0;

            while (time() <= $deadline && ! connection_aborted()) {
                $fresh = $document->fresh();
                if (! $fresh) {
                    $this->emitSseEvent('fiscal.updated', [
                        'message' => 'Documento fiscal não encontrado.',
                        'fiscal' => null,
                    ]);
                    break;
                }

                $version = $this->fiscalDocumentStreamVersion($fresh);
                if ($version !== $lastVersion) {
                    $lastVersion = $version;
                    $this->emitSseEvent('fiscal.updated', [
                        'message' => $this->resolveEmissionStatusLabel($fresh),
                        'fiscal' => $this->presentFiscalDocument($fresh, true, false),
                    ]);

                    if ($this->isFiscalStreamTerminal($fresh)) {
                        break;
                    }
                } elseif (time() - $lastHeartbeatAt >= 10) {
                    $lastHeartbeatAt = time();
                    $this->emitSseEvent('heartbeat', [
                        'ts' => now()->toIso8601String(),
                    ]);
                }

                usleep(500 * 1000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
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
        $binary = $this->resolveFiscalArtifactBinary($payload, $artifact);

        return response($binary, 200, [
            'Content-Type' => $artifact === 'xml' ? 'application/xml' : 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="sale-%s.%s"', $sale->numero, $artifact),
        ]);
    }

    private function resolveFiscalArtifactBinary(array $payload, string $artifact): string
    {
        $base64 = data_get($payload, 'base64');
        if ($artifact === 'pdf' && is_string($base64) && trim($base64) !== '') {
            $normalized = preg_replace('/^data:application\/pdf;base64,/', '', trim($base64));
            $decoded = base64_decode($normalized, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        $content = data_get($payload, 'content');
        if (is_string($content) && $content !== '') {
            return $content;
        }

        $raw = data_get($payload, 'raw');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        if (is_string($base64) && trim($base64) !== '') {
            $decoded = base64_decode(preg_replace('/\s+/', '', trim($base64)), true);

            return $decoded === false ? $base64 : $decoded;
        }

        return '';
    }

    private function isPafEnabled(?FiscalConfig $config): bool
    {
        return $config !== null && (bool) ($config->paf_enabled ?? false);
    }

    private function resolvePafReferences(array $payload, array $scope): array
    {
        $davId = trim((string) data_get($payload, 'complementary.paf_dav_id', ''));
        $preSaleId = trim((string) data_get($payload, 'complementary.paf_pre_sale_id', ''));
        $externalRequisitionId = trim((string) data_get($payload, 'complementary.paf_external_requisition_id', ''));

        $dav = null;
        if ($davId !== '') {
            $davQuery = PafDav::query()->lockForUpdate();
            if (config('pdv.mode') === 'erp') {
                $davQuery
                    ->where('grupo_empresarial_id', $scope['grupo_id'])
                    ->where('estabelecimento_id', $scope['estabelecimento_id']);
            }
            $dav = $davQuery->find($davId);
            if (! $dav) {
                throw ValidationException::withMessages([
                    'complementary.paf_dav_id' => ['DAV não encontrado.'],
                ]);
            }
            if ($dav->status !== PafDav::STATUS_OPEN || $dav->converted_sale_id !== null) {
                throw ValidationException::withMessages([
                    'complementary.paf_dav_id' => ['DAV já convertido em documento fiscal.'],
                ]);
            }
        }

        $preSale = null;
        if ($preSaleId !== '') {
            $preSale = PafPreSale::query()->lockForUpdate()->find($preSaleId);
            if (! $preSale) {
                throw ValidationException::withMessages([
                    'complementary.paf_pre_sale_id' => ['Pré-venda não encontrada.'],
                ]);
            }
            if ($preSale->status !== PafPreSale::STATUS_OPEN || $preSale->converted_sale_id !== null) {
                throw ValidationException::withMessages([
                    'complementary.paf_pre_sale_id' => ['Pré-venda já convertida em documento fiscal.'],
                ]);
            }
        }

        $externalRequisition = null;
        if ($externalRequisitionId !== '') {
            $externalRequisition = PafExternalRequisition::query()->lockForUpdate()->find($externalRequisitionId);
            if (! $externalRequisition) {
                throw ValidationException::withMessages([
                    'complementary.paf_external_requisition_id' => ['Requisição externa não encontrada.'],
                ]);
            }
            if ($externalRequisition->status !== PafExternalRequisition::STATUS_RECEIVED || $externalRequisition->attended_sale_id !== null) {
                throw ValidationException::withMessages([
                    'complementary.paf_external_requisition_id' => ['Requisição externa já atendida ou denegada.'],
                ]);
            }
        }

        return [
            'dav' => $dav,
            'pre_sale' => $preSale,
            'external_requisition' => $externalRequisition,
        ];
    }

    private function buildPafFiscalObservation(array $payload, array $references): ?string
    {
        $parts = [];
        $base = trim((string) (
            data_get($payload, 'complementary.fiscal_observation')
            ?: data_get($payload, 'complementary.observacao_nota')
            ?: ''
        ));
        if ($base !== '') {
            $parts[] = $base;
        }

        $dav = $references['dav'] ?? null;
        if ($dav instanceof PafDav) {
            $parts[] = '#DAV '.trim((string) $dav->number);
        }

        $externalRequisition = $references['external_requisition'] ?? null;
        if ($externalRequisition instanceof PafExternalRequisition) {
            $parts[] = 'RE '.trim((string) $externalRequisition->cre);
        }

        $observation = trim(implode(' | ', array_unique(array_filter($parts))));

        return $observation !== '' ? $observation : null;
    }

    private function assertPafSaleItemIsRegistered(
        array $item,
        ?array $catalogProduct,
        string $productName,
        ?string $productCode,
        string $unit,
    ): void {
        if (! $catalogProduct) {
            throw ValidationException::withMessages([
                'items' => ['No modo PAF-NFC-e, a venda de item fiscal exige produto cadastrado.'],
            ]);
        }

        if (trim($productName) === '' || trim((string) $productCode) === '' || trim($unit) === '') {
            $label = trim((string) ($item['nome'] ?? $productName ?: 'Produto'));
            throw ValidationException::withMessages([
                'items' => ["Produto {$label} sem código/GTIN, descrição ou unidade para venda no modo PAF-NFC-e."],
            ]);
        }
    }

    private function markPafReferencesAsConverted(array $references, Sale $sale, Carbon $now): void
    {
        $dav = $references['dav'] ?? null;
        if ($dav instanceof PafDav) {
            $dav->status = PafDav::STATUS_CONVERTED;
            $dav->converted_sale_id = $sale->id;
            $dav->converted_at = $now;
            $dav->save();
        }

        $preSale = $references['pre_sale'] ?? null;
        if ($preSale instanceof PafPreSale) {
            $preSale->status = PafPreSale::STATUS_CONVERTED;
            $preSale->converted_sale_id = $sale->id;
            $preSale->converted_at = $now;
            $preSale->save();
        }

        $externalRequisition = $references['external_requisition'] ?? null;
        if ($externalRequisition instanceof PafExternalRequisition) {
            $externalRequisition->status = PafExternalRequisition::STATUS_ATTENDED;
            $externalRequisition->attended_sale_id = $sale->id;
            $externalRequisition->attended_at = $now;
            $externalRequisition->save();
        }
    }

    private function recordPafDailyPaymentTotal(Sale $sale, string $methodName, float $amount, ?string $documentTypeCode = null): void
    {
        if (abs($amount) < 0.005) {
            return;
        }

        $movementDate = ($sale->sold_at ?? $sale->created_at ?? now())->toDateString();
        $customerDocument = preg_replace('/\D+/', '', (string) data_get($sale->customer_snapshot, 'cpf_cnpj', ''));
        if (! in_array(strlen($customerDocument), [11, 14], true)) {
            $customerDocument = null;
        }

        $paymentMethodName = mb_substr(trim($methodName) ?: 'Pagamento', 0, 25);
        $documentTypeCode = $documentTypeCode ?: $this->pafDocumentTypeCode((string) $sale->document_type);

        $query = PafDailyPaymentTotal::query()
            ->whereDate('movement_date', $movementDate)
            ->where('payment_method_name', $paymentMethodName)
            ->where('document_type_code', $documentTypeCode)
            ->whereNull('non_tax_document_number');

        $customerDocument === null
            ? $query->whereNull('customer_document')
            : $query->where('customer_document', $customerDocument);

        $total = $query->lockForUpdate()->first() ?? new PafDailyPaymentTotal([
            'movement_date' => $movementDate,
            'payment_method_name' => $paymentMethodName,
            'document_type_code' => $documentTypeCode,
            'customer_document' => $customerDocument,
            'non_tax_document_number' => null,
            'amount' => 0,
        ]);

        $total->amount = round((float) $total->amount + $amount, 2);
        $total->save();
    }

    private function pafDocumentTypeCode(string $documentType): string
    {
        return match (mb_strtolower($documentType)) {
            'nfce', 'nfe' => '1',
            default => '2',
        };
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
        $tableId = trim((string) ($ficha->table_id ?? ''));
        $isTablelessGroup = $tableId === ''
            && in_array($expectedTableId, ['__without_table__', 'without-table:'.$ficha->id], true);

        if ($expectedTableId !== '' && ! $isTablelessGroup && $tableId !== $expectedTableId) {
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
        if ($normalized === 'nfe') {
            return 'nfe';
        }

        return 'nfce';
    }

    private function normalizeCustomerSnapshot(mixed $customer): array
    {
        if (! is_array($customer)) {
            return [];
        }

        $document = preg_replace('/\D+/', '', (string) $this->firstFilled([
            data_get($customer, 'cpf_cnpj'),
            data_get($customer, 'cpfCnpj'),
            data_get($customer, 'documento'),
            data_get($customer, 'document'),
            data_get($customer, 'cnpj'),
            data_get($customer, 'cpf'),
        ]));
        $name = trim((string) data_get($customer, 'nome', data_get($customer, 'name', '')));

        if ($document === '' && ($name === '' || mb_strtolower($name) === 'consumidor final')) {
            return [];
        }

        return array_filter([
            'id' => data_get($customer, 'id'),
            'nome' => $name ?: null,
            'cpf_cnpj' => $document ?: null,
            'telefone' => preg_replace('/\D+/', '', (string) data_get($customer, 'telefone', data_get($customer, 'phone', ''))) ?: null,
            'email' => trim((string) data_get($customer, 'email', '')) ?: null,
            'tipo_pessoa' => data_get($customer, 'tipo_pessoa', data_get($customer, 'personType')),
            'cep' => preg_replace('/\D+/', '', (string) data_get($customer, 'cep', '')) ?: null,
            'logradouro' => data_get($customer, 'logradouro', data_get($customer, 'street')),
            'numero' => data_get($customer, 'numero', data_get($customer, 'number')),
            'bairro' => data_get($customer, 'bairro', data_get($customer, 'neighborhood')),
            'complemento' => data_get($customer, 'complemento', data_get($customer, 'complement')),
            'cidade' => data_get($customer, 'cidade', data_get($customer, 'city')),
            'uf' => strtoupper((string) (data_get($customer, 'uf') ?: data_get($customer, 'state'))) ?: null,
            'codigo_ibge' => data_get($customer, 'codigo_ibge'),
            'inscricao_estadual' => data_get($customer, 'inscricao_estadual', data_get($customer, 'stateRegistration')),
            'indicador_ie' => data_get($customer, 'indicador_ie', '9'),
            'pais' => data_get($customer, 'pais', data_get($customer, 'country')),
            'country_code' => data_get($customer, 'country_code'),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function hasIdentifiedCustomer(array $customer): bool
    {
        return trim((string) ($customer['nome'] ?? '')) !== ''
            && preg_match('/^\d{11}$|^\d{14}$/', (string) ($customer['cpf_cnpj'] ?? '')) === 1;
    }

    private function firstFilled(array $values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return '';
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

    private function presentFiscalDocument(?SaleFiscalDocument $document, bool $includeXmlBase64 = false, bool $allowXmlDownload = true): ?array
    {
        if (! $document) {
            return null;
        }

        $artifacts = is_array(data_get($document->response_payload, 'artifacts'))
            ? data_get($document->response_payload, 'artifacts')
            : [];
        $authorized = $document->status === SaleFiscalDocument::STATUS_AUTHORIZED;
        $embeddedXml = $this->resolveEmbeddedFiscalXml($document);
        $hasEmbeddedXml = $embeddedXml !== null;
        $xmlStatus = data_get($artifacts, 'xml_status');
        $pdfStatus = data_get($artifacts, 'pdf_status');
        $xmlAvailable = $authorized
            && (
                $hasEmbeddedXml
                || (
                    data_get($artifacts, 'xml_available', true) !== false
                    && ! in_array($xmlStatus, ['processing', 'unavailable'], true)
                )
            );
        $pdfAvailable = $authorized
            && data_get($artifacts, 'pdf_available', true) !== false
            && ! in_array($pdfStatus, ['processing', 'unavailable'], true);

        $data = [
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
            'artifacts' => $artifacts ?: null,
            'xml_available' => $xmlAvailable,
            'pdf_available' => $pdfAvailable,
            'xml_status' => $xmlStatus,
            'pdf_status' => $pdfStatus,
            'xml_url' => $xmlAvailable ? "/sales/{$document->sale_id}/fiscal/xml" : null,
            'pdf_url' => $pdfAvailable ? "/sales/{$document->sale_id}/fiscal/pdf" : null,
            'debug' => data_get($document->response_payload, '_debug'),
        ];

        if ($includeXmlBase64 && $xmlAvailable && ($hasEmbeddedXml || $allowXmlDownload)) {
            $data['xml_base64'] = $hasEmbeddedXml
                ? base64_encode($embeddedXml)
                : $this->resolveFiscalXmlBase64($document);
            $data['xml_mime_type'] = 'application/xml';
        }

        return $data;
    }

    private function resolveEmbeddedFiscalXml(SaleFiscalDocument $document): ?string
    {
        if ($document->status !== SaleFiscalDocument::STATUS_AUTHORIZED) {
            return null;
        }

        $xml = data_get($document->response_payload, 'xml')
            ?: data_get($document->response_payload, 'document.xml')
            ?: data_get($document->response_payload, 'data.document.xml');

        return is_string($xml) && trim($xml) !== '' ? $xml : null;
    }

    private function resolveFiscalXmlBase64(SaleFiscalDocument $document): ?string
    {
        $embeddedXml = $this->resolveEmbeddedFiscalXml($document);
        if ($embeddedXml !== null) {
            return base64_encode($embeddedXml);
        }

        try {
            $payload = $this->notaAgil->download($document, 'xml', FiscalConfig::query()->first());
        } catch (Throwable $error) {
            report($error);

            return null;
        }

        $base64 = data_get($payload, 'base64');
        if (is_string($base64) && trim($base64) !== '') {
            return preg_replace('/^data:[^;]+;base64,/', '', trim($base64));
        }

        $content = data_get($payload, 'content') ?: data_get($payload, 'raw');
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        if ((bool) data_get($payload, 'is_base64') || preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $content)) {
            return preg_replace('/\s+/', '', trim($content));
        }

        return base64_encode($content);
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

    private function normalizeSaleItemUnit(mixed $value): string
    {
        $unit = mb_strtoupper(trim((string) $value));

        return $unit !== '' ? $unit : 'UN';
    }

    private function emitSseEvent(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    private function fiscalDocumentStreamVersion(SaleFiscalDocument $document): string
    {
        return implode('|', [
            $document->updated_at?->getTimestamp() ?? 0,
            $document->status,
            $document->fiscal_status,
            $document->operational_status,
            $document->access_key,
            $document->protocol,
            hash('sha256', (string) data_get($document->response_payload, 'xml', '')),
        ]);
    }

    private function isFiscalStreamTerminal(SaleFiscalDocument $document): bool
    {
        return in_array($document->status, [
            SaleFiscalDocument::STATUS_AUTHORIZED,
            SaleFiscalDocument::STATUS_REJECTED,
            SaleFiscalDocument::STATUS_CONTINGENCY_PENDING,
            SaleFiscalDocument::STATUS_CANCELLED,
        ], true);
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
        if ($normalized === 'nfce') {
            return 'NFC-e';
        }
        if ($normalized === 'nfe') {
            return 'NF-e';
        }

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
