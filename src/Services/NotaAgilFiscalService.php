<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\Sale;
use Freeline\Pdv\Models\SaleFiscalDocument;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use NotaAgil\Integration\NotaAgilApiException;
use NotaAgil\Integration\NotaAgilClient;
use Throwable;

class NotaAgilFiscalService
{
    public function isEnabled(?FiscalConfig $config): bool
    {
        return (bool) ($config?->notagil_enabled ?? false)
            && trim((string) config('pdv.notagil.token')) !== '';
    }

    public function operationCode(FiscalConfig $config, string $documentType): ?string
    {
        $code = $documentType === 'nfe'
            ? $config->notagil_operation_code_nfe
            : $config->notagil_operation_code_nfce;

        return trim((string) $code) ?: null;
    }

    public function companyId(?FiscalConfig $config): ?string
    {
        return trim((string) ($config?->notagil_company_id ?? '')) ?: null;
    }

    public function previewCheckout(
        array $checkoutPayload,
        FiscalConfig $config,
        string $documentType,
        string $series,
        int $number,
        Carbon $issuedAt,
        ?int $operatorId,
    ): array {
        $operationCode = $this->operationCode($config, $documentType);
        if (! $operationCode) {
            throw new NotaAgilConfigurationException('Configure o código de operação NotaAgil para '.strtoupper($documentType).'.');
        }

        return $this->client()->previewDocumentByOperation(
            $operationCode,
            $this->buildCheckoutPayload($checkoutPayload, $config, $documentType, $series, $number, $issuedAt, $operatorId),
            $this->companyId($config),
        );
    }

    public function makeFiscalDocument(Sale $sale, FiscalConfig $config, string $series, string $operationCode): SaleFiscalDocument
    {
        $externalId = 'pdv-sale-'.$sale->id;
        $document = new SaleFiscalDocument([
            'sale_id' => $sale->id,
            'document_type' => (string) $sale->document_type,
            'environment' => (string) ($config->ambiente ?: 'homologacao'),
            'series' => $series,
            'number' => (int) $sale->numero,
            'operation_code' => $operationCode,
            'external_id' => $externalId,
            'idempotency_key' => $externalId,
            'status' => SaleFiscalDocument::STATUS_PENDING,
        ]);

        $document->request_payload = $this->buildSalePayload($sale, $document, $config);

        return $document;
    }

    public function submitAndWait(SaleFiscalDocument $document, ?FiscalConfig $config = null): SaleFiscalDocument
    {
        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_PROCESSING,
            'attempts' => ((int) $document->attempts) + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        $response = $this->client()->createDocumentByOperation(
            $this->companyId($config),
            $document->operation_code,
            $document->request_payload ?? [],
            $document->idempotency_key,
        );

        $document = $this->applyRemoteResponse($document, $response);

        $waitSeconds = max(0, (int) config('pdv.notagil.wait_seconds', 8));
        if ($waitSeconds > 0 && $document->status === SaleFiscalDocument::STATUS_PROCESSING) {
            $document = $this->sync($document, $config, $waitSeconds);
        }

        return $document;
    }

    public function sync(SaleFiscalDocument $document, ?FiscalConfig $config = null, ?int $timeoutSeconds = null): SaleFiscalDocument
    {
        $client = $this->client();
        $response = $timeoutSeconds === null
            ? $client->document($document->external_id, $this->companyId($config))
            : $client->waitDocument($document->external_id, $this->companyId($config), $timeoutSeconds);

        return $this->applyRemoteResponse($document, $response);
    }

    public function markContingency(SaleFiscalDocument $document, Throwable|string $reason): SaleFiscalDocument
    {
        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_CONTINGENCY_PENDING,
            'last_error' => $reason instanceof Throwable ? $this->exceptionMessage($reason) : $reason,
            'next_retry_at' => now()->addMinutes(5),
            'contingency_printed_at' => now(),
        ])->save();

        return $document;
    }

    public function markRejected(SaleFiscalDocument $document, Throwable|string $reason): SaleFiscalDocument
    {
        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_REJECTED,
            'last_error' => $reason instanceof Throwable ? $this->exceptionMessage($reason) : $reason,
        ])->save();

        return $document;
    }

    public function cancel(SaleFiscalDocument $document, string $reason, ?FiscalConfig $config = null): SaleFiscalDocument
    {
        $response = $this->client()->cancelDocument($document->external_id, $reason, $this->companyId($config));

        $document = $this->applyRemoteResponse($document, $response);
        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_CANCELLED,
        ])->save();

        return $document;
    }

    public function download(SaleFiscalDocument $document, string $artifact, ?FiscalConfig $config = null): array
    {
        return $artifact === 'xml'
            ? $this->client()->downloadDocumentXml($document->external_id, $this->companyId($config))
            : $this->client()->downloadDocumentPdf($document->external_id, $this->companyId($config));
    }

    public function isFiscalValidationError(Throwable $error): bool
    {
        return $error instanceof NotaAgilApiException
            && $error->statusCode >= 400
            && $error->statusCode < 500;
    }

    public function exceptionMessage(Throwable $error): string
    {
        if ($error instanceof NotaAgilApiException && is_array($error->payload)) {
            $details = data_get($error->payload, 'errors')
                ?: data_get($error->payload, 'message')
                ?: data_get($error->payload, 'error');

            if (is_array($details)) {
                return json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $error->getMessage();
            }

            if (is_string($details) && trim($details) !== '') {
                return trim($details);
            }
        }

        return $error->getMessage();
    }

    private function buildCheckoutPayload(
        array $checkoutPayload,
        FiscalConfig $config,
        string $documentType,
        string $series,
        int $number,
        Carbon $issuedAt,
        ?int $operatorId,
    ): array {
        return $this->envelope(
            externalId: 'pdv-preview-'.(string) Str::uuid(),
            documentType: $documentType,
            config: $config,
            series: $series,
            number: $number,
            issuedAt: $issuedAt,
            total: (float) data_get($checkoutPayload, 'totals.payable_total', data_get($checkoutPayload, 'totals.net_total', 0)),
            counterparty: $this->counterparty($checkoutPayload['customer'] ?? null),
            items: collect($checkoutPayload['items'] ?? [])->map(fn (array $item): array => $this->mapInputItem($item))->values()->all(),
            metadata: [
                'origin' => 'pdv',
                'operator_id' => $operatorId,
                'payments' => $checkoutPayload['payments'] ?? [],
                'restaurant' => [
                    'ficha_id' => data_get($checkoutPayload, 'complementary.restaurant_ficha_id'),
                    'ficha_code' => data_get($checkoutPayload, 'complementary.restaurant_ficha_code'),
                    'table_id' => data_get($checkoutPayload, 'complementary.restaurant_table_id'),
                    'table_code' => data_get($checkoutPayload, 'complementary.restaurant_table_code'),
                ],
            ],
        );
    }

    private function buildSalePayload(Sale $sale, SaleFiscalDocument $document, FiscalConfig $config): array
    {
        $sale->loadMissing([
            'items.product',
            'items.catalogProduct.fiscalItemProfile',
            'items.catalogProduct.fiscalItemProfileSaida',
            'items.catalogProduct.unidadeMedida',
            'payments',
        ]);

        return $this->envelope(
            externalId: $document->external_id,
            documentType: $document->document_type,
            config: $config,
            series: (string) $document->series,
            number: (int) $document->number,
            issuedAt: $sale->sold_at ?? now(),
            total: (float) $sale->total_financeiro,
            counterparty: $this->counterparty(['nome' => $sale->cliente_nome]),
            items: $sale->items->map(fn ($item): array => $this->mapSaleItem($item))->values()->all(),
            metadata: [
                'origin' => 'pdv',
                'sale_id' => $sale->id,
                'sale_number' => $sale->numero,
                'operator_id' => $sale->created_by,
                'payments' => $sale->payments->map(fn ($payment): array => [
                    'method_name' => $payment->metodo_nome,
                    'description' => $payment->descricao,
                    'amount' => (float) $payment->valor,
                ])->values()->all(),
            ],
        );
    }

    private function envelope(
        string $externalId,
        string $documentType,
        FiscalConfig $config,
        string $series,
        int $number,
        Carbon $issuedAt,
        float $total,
        array $counterparty,
        array $items,
        array $metadata,
    ): array {
        return [
            'external_id' => $externalId,
            'document_type' => $documentType,
            'metadata' => $metadata,
            'snapshot' => [
                'fiscal_environment' => (string) ($config->ambiente ?: 'homologacao'),
                'document_direction' => 'saida',
                'reference_date' => $issuedAt->toDateString(),
                'document_data' => [
                    'serie' => $series,
                    'numero' => str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'data_emissao' => $issuedAt->toIso8601String(),
                    'natureza_operacao' => 'Venda de mercadoria',
                    'valor_total' => round($total, 2),
                ],
                'counterparty' => $counterparty,
                'document_references' => [],
                'items' => $items,
            ],
        ];
    }

    private function mapInputItem(array $item): array
    {
        $quantity = round((float) ($item['quantidade'] ?? 0), 3);
        $unitPrice = round((float) ($item['valor_unitario'] ?? 0), 2);
        $product = $this->resolveCatalogProduct($item['id'] ?? null);

        return $this->mapFiscalItem(
            productId: $product?->id,
            sku: (string) ($item['codigo'] ?? $product?->cod_sku ?? $product?->codigo_operacional ?? ''),
            description: (string) ($item['nome'] ?? $product?->descricao ?? 'Produto'),
            quantity: $quantity,
            unitPrice: $unitPrice,
            product: $product,
        );
    }

    private function mapSaleItem($item): array
    {
        $product = $item->catalogProduct ?: null;

        return $this->mapFiscalItem(
            productId: $product?->id,
            sku: (string) ($item->produto_codigo ?? $product?->cod_sku ?? $product?->codigo_operacional ?? ''),
            description: (string) ($item->produto_nome ?: $product?->descricao ?: 'Produto'),
            quantity: (float) $item->quantidade,
            unitPrice: (float) $item->valor_unitario,
            product: $product,
            fallbackUnit: (string) ($item->unidade ?: 'UN'),
        );
    }

    private function mapFiscalItem(
        mixed $productId,
        string $sku,
        string $description,
        float $quantity,
        float $unitPrice,
        ?Produto $product = null,
        string $fallbackUnit = 'UN',
    ): array {
        $profile = $product?->fiscalItemProfileSaida ?: $product?->fiscalItemProfile;
        $attributes = is_array($product?->atributos_logisticos) ? $product->atributos_logisticos : [];
        $ncm = $profile?->ncm ?: data_get($attributes, 'fiscal_ncm');

        return array_filter([
            'product_id' => data_get($attributes, 'notagil_product_id'),
            'external_product_id' => $productId ? (string) $productId : null,
            'sku' => trim($sku) ?: null,
            'description' => trim($description) ?: 'Produto',
            'ncm' => $ncm ? preg_replace('/\D+/', '', (string) $ncm) : null,
            'cest' => $profile?->cest ? preg_replace('/\D+/', '', (string) $profile->cest) : null,
            'origin_code' => (string) ($profile?->origem_mercadoria ?: data_get($attributes, 'fiscal_origem', '0')),
            'tax_classification_code' => $profile?->cod_classe_tributo ?: data_get($attributes, 'fiscal_tax_classification_code'),
            'unit' => strtoupper((string) ($product?->unidadeMedida?->codigo_fiscal ?: $product?->unidadeMedida?->unidade ?: $fallbackUnit ?: 'UN')),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => round($quantity * $unitPrice, 2),
            'discount_amount' => 0,
            'freight_amount' => 0,
            'insurance_amount' => 0,
            'other_amount' => 0,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function resolveCatalogProduct(mixed $id): ?Produto
    {
        $id = trim((string) $id);
        if ($id === '') return null;

        return Produto::query()
            ->with(['fiscalItemProfile', 'fiscalItemProfileSaida', 'unidadeMedida'])
            ->find($id);
    }

    private function counterparty(mixed $customer): array
    {
        $customer = is_array($customer) ? $customer : [];
        $document = preg_replace('/\D+/', '', (string) data_get($customer, 'cpf_cnpj', data_get($customer, 'document', '')));
        $name = trim((string) data_get($customer, 'nome', ''));

        if ($document === '' && ($name === '' || mb_strtolower($name) === 'consumidor final')) {
            return [
                'buyer_identified' => false,
                'final_consumer' => true,
                'indicador_ie' => '9',
            ];
        }

        return array_filter([
            'nome' => $name ?: 'Consumidor final',
            'documento' => $document ?: null,
            'person_type' => strlen($document) > 11 ? 'pj' : 'pf',
            'uf' => data_get($customer, 'uf') ?: data_get($customer, 'state'),
            'codigo_ibge' => data_get($customer, 'codigo_ibge'),
            'indicador_ie' => data_get($customer, 'indicador_ie', '9'),
            'inscricao_estadual' => data_get($customer, 'inscricao_estadual'),
            'final_consumer' => true,
            'buyer_identified' => true,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function applyRemoteResponse(SaleFiscalDocument $document, array $response): SaleFiscalDocument
    {
        $fiscalStatus = (string) data_get($response, 'fiscal_status', data_get($response, 'status_fiscal', ''));
        $operationalStatus = (string) data_get($response, 'operational_status', data_get($response, 'status_operacional', ''));
        $status = $this->normalizeStatus($fiscalStatus, $operationalStatus);

        $document->forceFill([
            'status' => $status,
            'fiscal_status' => $fiscalStatus ?: $document->fiscal_status,
            'operational_status' => $operationalStatus ?: $document->operational_status,
            'access_key' => data_get($response, 'access_key', data_get($response, 'chave_acesso', $document->access_key)),
            'protocol' => data_get($response, 'protocol', data_get($response, 'protocolo', $document->protocol)),
            'authorized_at' => $this->resolveAuthorizedAt($response, $status) ?: $document->authorized_at,
            'response_payload' => $response,
            'last_error' => $status === SaleFiscalDocument::STATUS_REJECTED
                ? (string) (data_get($response, 'rejection_reason', data_get($response, 'message', $document->last_error ?? 'Documento fiscal rejeitado.')))
                : null,
            'next_retry_at' => $status === SaleFiscalDocument::STATUS_PROCESSING ? now()->addMinute() : null,
        ])->save();

        return $document;
    }

    private function normalizeStatus(string $fiscalStatus, string $operationalStatus): string
    {
        $fiscal = mb_strtolower($fiscalStatus);
        $operational = mb_strtolower($operationalStatus);

        if (in_array($fiscal, ['authorized', 'autorizada', 'autorizado'], true)) return SaleFiscalDocument::STATUS_AUTHORIZED;
        if (in_array($fiscal, ['rejected', 'rejeitada', 'denied', 'denegada'], true) || $operational === 'failed') return SaleFiscalDocument::STATUS_REJECTED;
        if (in_array($fiscal, ['cancelled', 'canceled', 'cancelada', 'cancelado'], true)) return SaleFiscalDocument::STATUS_CANCELLED;

        return SaleFiscalDocument::STATUS_PROCESSING;
    }

    private function resolveAuthorizedAt(array $response, string $status): ?Carbon
    {
        $value = data_get($response, 'authorized_at', data_get($response, 'autorizado_em'));
        if ($value) return Carbon::parse($value);

        return $status === SaleFiscalDocument::STATUS_AUTHORIZED ? now() : null;
    }

    private function client(): NotaAgilClient
    {
        return new NotaAgilClient(
            baseUrl: (string) config('pdv.notagil.base_url', 'https://api.notagil.com.br/api/v1/integrations'),
            token: (string) config('pdv.notagil.token'),
            http: new Client(['timeout' => max(1, (int) config('pdv.notagil.timeout', 30))]),
        );
    }
}

class NotaAgilConfigurationException extends \RuntimeException
{
}
