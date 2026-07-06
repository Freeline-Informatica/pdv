<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\Product;
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
    public const WEBHOOK_EVENTS = [
        'fiscal_document.created',
        'fiscal_document.authorized',
        'fiscal_document.rejected',
        'fiscal_document.failed',
        'fiscal_document.cancelled',
        'fiscal_document.corrected',
    ];

    public function isEnabled(?FiscalConfig $config): bool
    {
        return (bool) ($config?->notagil_enabled ?? false)
            && $this->token($config) !== '';
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

    public function provisionWebhook(FiscalConfig $config, ?string $url = null): array
    {
        if ($this->token($config) === '') {
            throw new NotaAgilConfigurationException('Configure o token NotaAgil antes de criar o webhook.');
        }

        $url = trim((string) ($url ?: $config->notagil_webhook_url ?: config('pdv.notagil.webhook_url')));
        if ($url === '') {
            throw new NotaAgilConfigurationException('Informe a URL pública do webhook NotaAgil.');
        }

        $payload = array_filter([
            'url' => $url,
            'descricao' => 'PDV Freeline - atualização fiscal',
            'situacao' => 'ativo',
            'ambiente' => $this->notagilEnvironment($config, allowAll: true),
            'eventos' => self::WEBHOOK_EVENTS,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $webhookId = trim((string) ($config->notagil_webhook_id ?? ''));
        $client = $this->client($config);
        if ($webhookId !== '') {
            try {
                $response = $client->updateWebhookV2($webhookId, $payload);
            } catch (NotaAgilApiException $exception) {
                if ($exception->statusCode !== 404) {
                    throw $exception;
                }

                $response = $client->createWebhookV2($payload);
            }
        } else {
            $response = $client->createWebhookV2($payload);
        }

        return $this->normalizeWebhookResponse($response, $payload);
    }

    public function rotateWebhookSecret(FiscalConfig $config): array
    {
        if ($this->token($config) === '') {
            throw new NotaAgilConfigurationException('Configure o token NotaAgil antes de rotacionar o segredo do webhook.');
        }

        $webhookId = trim((string) ($config->notagil_webhook_id ?? ''));
        if ($webhookId === '') {
            throw new NotaAgilConfigurationException('Sincronize o webhook NotaAgil antes de rotacionar o segredo.');
        }

        try {
            $response = $this->client($config)->rotateWebhookSecretV2($webhookId);
        } catch (NotaAgilApiException $exception) {
            if ($exception->statusCode === 404) {
                throw new NotaAgilConfigurationException('Webhook NotaAgil não encontrado. Sincronize o webhook antes de rotacionar o segredo.');
            }

            throw $exception;
        }

        return $this->normalizeWebhookResponse($response, [
            'id' => $webhookId,
            'url' => $config->notagil_webhook_url,
            'situacao' => $config->notagil_webhook_status ?: 'ativo',
            'ambiente' => $this->notagilEnvironment($config, allowAll: true),
            'eventos' => self::WEBHOOK_EVENTS,
        ]);
    }

    public function requireCompanyId(?FiscalConfig $config): string
    {
        $companyId = $this->companyId($config);
        if (! $companyId) {
            throw new NotaAgilConfigurationException('Configure o Company ID NotaAgil antes de emitir documentos fiscais.');
        }

        return $companyId;
    }

    public function searchNcms(array $filters = [], ?FiscalConfig $config = null): array
    {
        if ($this->token($config) === '') {
            throw new NotaAgilConfigurationException('Configure o token NotaAgil antes de consultar NCMs.');
        }

        return $this->client($config)->ncmsV2(array_filter([
            'busca' => $filters['busca'] ?? $filters['search'] ?? $filters['q'] ?? null,
            'limite' => $filters['limite'] ?? $filters['limit'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== ''));
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
        $startedAt = microtime(true);

        $document = $this->submit($document, $config);

        $waitSeconds = max(0, (int) config('pdv.notagil.wait_seconds', 8));
        if ($waitSeconds > 0 && $document->status === SaleFiscalDocument::STATUS_PROCESSING) {
            $syncStartedAt = microtime(true);
            $document = $this->sync($document, $config, $waitSeconds, [
                'submit_attempt' => (int) $document->attempts,
                'create_ms' => data_get($document->response_payload, '_debug.create_ms'),
                'wait_authorization_timeout_s' => $waitSeconds,
            ]);
            $document = $this->mergeDebug($document, [
                'wait_authorization_ms' => $this->elapsedMs($syncStartedAt),
            ]);
        }

        return $this->mergeDebug($document, [
            'total_submit_wait_ms' => $this->elapsedMs($startedAt),
        ]);
    }

    public function submit(SaleFiscalDocument $document, ?FiscalConfig $config = null): SaleFiscalDocument
    {
        $startedAt = microtime(true);
        $config ??= FiscalConfig::query()->first();

        $currentPayload = $document->request_payload ?? [];
        if ($config && $document->sale_id && ! $this->isOperationDocumentV2Payload($currentPayload)) {
            $sale = $document->sale()->first();
            if ($sale) {
                $document->request_payload = $this->buildSalePayload($sale, $document, $config);
            }
        }

        $payload = $document->request_payload ?? [];
        if ($this->isOperationDocumentV2Payload($payload)) {
            $payload = $this->operationPayload($payload, $this->operationPayloadIsSynchronous($payload, $document, $config));
            $document->request_payload = $payload;
        } elseif (is_array(data_get($payload, 'snapshot')) || is_array(data_get($payload, 'payload'))) {
            $payload = $this->operationPayload($payload, $this->operationPayloadIsSynchronous($payload, $document, $config));
            $document->request_payload = $payload;
        }
        $this->assertOperationDocumentCanBeIssued($payload, $document);

        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_PROCESSING,
            'attempts' => ((int) $document->attempts) + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        $createStartedAt = microtime(true);
        $client = $this->client($config);
        $response = $client->createDocumentByOperationV2((string) $document->operation_code, $payload, $document->idempotency_key);

        $response = $this->withDebug($response, [
            'submit_attempt' => (int) $document->attempts,
            'create_ms' => $this->elapsedMs($createStartedAt),
            'synchronous_operation' => data_get($payload, 'modo_emissao') === 'sincrono',
        ]);
        $document = $this->applyRemoteResponse($document, $response);

        return $this->mergeDebug($document, [
            'total_submit_ms' => $this->elapsedMs($startedAt),
        ]);
    }

    private function isOperationDocumentV2Payload(array $payload): bool
    {
        return data_get($payload, 'tipo_documento') !== null
            && is_array(data_get($payload, 'retrato'));
    }

    private function assertOperationDocumentCanBeIssued(array $payload, SaleFiscalDocument $document): void
    {
        $operationCode = trim((string) $document->operation_code);
        if ($operationCode === '' || $operationCode === 'direto') {
            $label = mb_strtolower((string) $document->document_type) === 'nfe' ? 'NF-e' : 'NFC-e';
            throw new NotaAgilConfigurationException("Operation code NotaAgil ausente para {$label}. Configure o código técnico da operação fiscal antes de emitir por operação v2.");
        }

        if (! $this->isOperationDocumentV2Payload($payload)) {
            throw new NotaAgilConfigurationException('Payload fiscal NotaAgil V2 por operação inválido. Recrie o documento fiscal antes de emitir.');
        }

        $items = data_get($payload, 'retrato.itens', []);
        if (! is_array($items) || $items === []) {
            throw new NotaAgilConfigurationException('Payload fiscal NotaAgil V2 por operação sem itens. Recrie o documento fiscal antes de emitir.');
        }
    }

    private function operationPayloadIsSynchronous(array $payload, SaleFiscalDocument $document, ?FiscalConfig $config): bool
    {
        $synchronous = data_get($payload, 'sincrono');
        if (is_bool($synchronous)) {
            return $synchronous;
        }

        $mode = data_get($payload, 'modo_emissao');
        if ($mode !== null) {
            return mb_strtolower(trim((string) $mode)) === 'sincrono';
        }

        return $this->shouldSubmitSynchronouslyForType((string) $document->document_type, $config);
    }

    private function operationPayload(array $payload, bool $synchronous): array
    {
        $documentType = mb_strtolower(trim((string) (data_get($payload, 'tipo_documento') ?: data_get($payload, 'document_type'))));
        $metadata = data_get($payload, 'metadados', data_get($payload, 'metadata'));
        $snapshot = data_get($payload, 'retrato', data_get($payload, 'snapshot'));
        $directPayload = data_get($payload, 'payload');
        if (! is_array($snapshot) && is_array($directPayload)) {
            $snapshot = $this->snapshotFromDirectPayload($directPayload, $payload);
        }
        if (is_array($snapshot)) {
            $snapshot = $this->normalizeOperationSnapshot($snapshot);
        }

        return $this->filterBlank([
            'external_id' => data_get($payload, 'external_id'),
            'tipo_documento' => $documentType !== '' ? $documentType : null,
            'municipio' => data_get($payload, 'municipio'),
            'modo_emissao' => $synchronous ? 'sincrono' : 'fila',
            'sincrono' => $synchronous,
            'retrato' => is_array($snapshot) ? $snapshot : null,
            'metadados' => is_array($metadata) ? $this->normalizeOperationMetadata($metadata) : null,
        ]);
    }

    private function normalizeOperationMetadata(array $metadata): array
    {
        $knownKeys = [
            'origin', 'origem',
            'sale_id', 'venda_id',
            'sale_number', 'numero_venda',
            'operator_id', 'operador_id',
            'payments', 'pagamentos',
            'restaurant', 'restaurante',
            'fiscal_observation', 'observacao_fiscal',
            'ibpt_message', 'mensagem_ibpt',
        ];
        $extra = array_diff_key($metadata, array_flip($knownKeys));

        return $this->filterBlank([
            'origem' => data_get($metadata, 'origem', data_get($metadata, 'origin')),
            'venda_id' => data_get($metadata, 'venda_id', data_get($metadata, 'sale_id')),
            'numero_venda' => data_get($metadata, 'numero_venda', data_get($metadata, 'sale_number')),
            'operador_id' => data_get($metadata, 'operador_id', data_get($metadata, 'operator_id')),
            'observacao_fiscal' => data_get($metadata, 'observacao_fiscal', data_get($metadata, 'fiscal_observation')),
            'mensagem_ibpt' => data_get($metadata, 'mensagem_ibpt', data_get($metadata, 'ibpt_message')),
            'pagamentos' => $this->normalizeOperationPayments(data_get($metadata, 'pagamentos', data_get($metadata, 'payments', []))),
            'restaurante' => data_get($metadata, 'restaurante', data_get($metadata, 'restaurant')),
            ...$extra,
        ]);
    }

    private function normalizeOperationPayments(mixed $payments): ?array
    {
        if (! is_array($payments) || $payments === []) {
            return null;
        }

        $normalized = [];
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $normalized[] = $this->filterBlank([
                'meio' => data_get($payment, 'meio', data_get($payment, 'method_name')),
                'descricao' => data_get($payment, 'descricao', data_get($payment, 'description')),
                'valor' => data_get($payment, 'valor', data_get($payment, 'amount')),
                'intermediador_cnpj' => data_get($payment, 'intermediador_cnpj', data_get($payment, 'intermediator_cnpj')),
                'identificador_estabelecimento' => data_get($payment, 'identificador_estabelecimento', data_get($payment, 'establishment_identifier')),
                'tipo_documento_paf' => data_get($payment, 'tipo_documento_paf', data_get($payment, 'paf_document_type_code')),
            ]);
        }

        return $normalized !== [] ? $normalized : null;
    }

    private function normalizeOperationSnapshot(array $snapshot): array
    {
        $documentData = data_get($snapshot, 'dados_documento', data_get($snapshot, 'document_data', []));
        $counterparty = data_get($snapshot, 'tomador', data_get($snapshot, 'contraparte', data_get($snapshot, 'counterparty', [])));
        $items = data_get($snapshot, 'itens', data_get($snapshot, 'items', []));

        return $this->filterBlank([
            'ambiente_fiscal' => data_get($snapshot, 'ambiente_fiscal', data_get($snapshot, 'fiscal_environment')),
            'direcao_documento' => data_get($snapshot, 'direcao_documento', data_get($snapshot, 'document_direction')),
            'data_referencia' => data_get($snapshot, 'data_referencia', data_get($snapshot, 'reference_date')),
            'dados_documento' => is_array($documentData) ? $documentData : null,
            'tomador' => $this->normalizeOperationCounterparty($counterparty),
            'referencias_documento' => data_get($snapshot, 'referencias_documento', data_get($snapshot, 'document_references', data_get($snapshot, 'referencias', []))),
            'itens' => is_array($items) ? $this->normalizeOperationItems($items) : [],
        ]);
    }

    private function normalizeOperationCounterparty(mixed $counterparty): array
    {
        if (! is_array($counterparty) || $counterparty === []) {
            return [
                'comprador_identificado' => false,
                'consumidor_final' => true,
                'indicador_ie' => '9',
            ];
        }

        $document = trim((string) data_get($counterparty, 'documento', ''));
        $buyerIdentified = data_get($counterparty, 'comprador_identificado', data_get($counterparty, 'buyer_identified'));

        return $this->filterBlank([
            'comprador_identificado' => $buyerIdentified !== null ? $buyerIdentified : $document !== '',
            'consumidor_final' => data_get($counterparty, 'consumidor_final', data_get($counterparty, 'final_consumer', true)),
            'indicador_ie' => data_get($counterparty, 'indicador_ie'),
            'nome' => data_get($counterparty, 'nome'),
            'documento' => $document,
            'tipo_pessoa' => $this->normalizeOperationPersonType(data_get($counterparty, 'tipo_pessoa', data_get($counterparty, 'person_type'))),
            'email' => data_get($counterparty, 'email'),
            'telefone' => data_get($counterparty, 'telefone'),
            'uf' => data_get($counterparty, 'uf'),
            'municipio' => data_get($counterparty, 'municipio'),
            'codigo_ibge' => data_get($counterparty, 'codigo_ibge'),
            'cep' => data_get($counterparty, 'cep'),
            'logradouro' => data_get($counterparty, 'logradouro'),
            'numero' => data_get($counterparty, 'numero'),
            'bairro' => data_get($counterparty, 'bairro'),
            'complemento' => data_get($counterparty, 'complemento'),
            'inscricao_estadual' => data_get($counterparty, 'inscricao_estadual'),
            'codigo_pais' => data_get($counterparty, 'codigo_pais', data_get($counterparty, 'country_code')),
        ]);
    }

    private function normalizeOperationPersonType(mixed $type): ?string
    {
        return match (mb_strtolower(trim((string) $type))) {
            'pf', 'fisica', 'pessoa_fisica', 'pessoa fisica' => 'fisica',
            'pj', 'juridica', 'pessoa_juridica', 'pessoa juridica' => 'juridica',
            'estrangeiro', 'estrangeira' => 'estrangeiro',
            default => null,
        };
    }

    private function normalizeOperationItems(array $items): array
    {
        return array_values(array_filter(array_map(function (mixed $item): ?array {
            return is_array($item) ? $this->normalizeOperationItem($item) : null;
        }, $items)));
    }

    private function normalizeOperationItem(array $item): array
    {
        $productId = data_get($item, 'produto_id', data_get($item, 'product_id'));

        return $this->filterBlank([
            'produto_id' => is_numeric($productId) ? (int) $productId : null,
            'produto_externo_id' => is_numeric($productId) ? null : $productId,
            'codigo' => data_get($item, 'codigo', data_get($item, 'sku')),
            'descricao' => data_get($item, 'descricao', data_get($item, 'description', 'Produto')),
            'tipo_item' => $this->normalizeOperationItemType(data_get($item, 'tipo_item', data_get($item, 'item_type', 'produto'))),
            'ncm' => data_get($item, 'ncm'),
            'cest' => data_get($item, 'cest'),
            'origem_codigo' => data_get($item, 'origem_codigo', data_get($item, 'origin_code', data_get($item, 'codigo_origem'))),
            'codigo_servico' => data_get($item, 'codigo_servico', data_get($item, 'service_code')),
            'codigo_nbs' => data_get($item, 'codigo_nbs'),
            'tags_fiscais' => data_get($item, 'tags_fiscais', data_get($item, 'fiscal_tags')),
            'codigo_classificacao_tributaria' => data_get($item, 'codigo_classificacao_tributaria', data_get($item, 'tax_classification_code')),
            'unidade' => data_get($item, 'unidade', data_get($item, 'unit')),
            'quantidade' => data_get($item, 'quantidade', data_get($item, 'quantity')),
            'valor_unitario' => data_get($item, 'valor_unitario', data_get($item, 'unit_price')),
            'valor_bruto' => data_get($item, 'valor_bruto', data_get($item, 'gross_amount')),
            'valor_desconto' => data_get($item, 'valor_desconto', data_get($item, 'discount_amount')),
            'valor_frete' => data_get($item, 'valor_frete', data_get($item, 'freight_amount')),
            'valor_seguro' => data_get($item, 'valor_seguro', data_get($item, 'insurance_amount')),
            'valor_outras_despesas' => data_get($item, 'valor_outras_despesas', data_get($item, 'other_amount', data_get($item, 'outros_valores'))),
        ]);
    }

    private function normalizeOperationItemType(mixed $type): ?string
    {
        return match (mb_strtolower(trim((string) $type))) {
            'product', 'produto', 'mercadoria' => 'produto',
            'service', 'servico', 'serviço' => 'servico',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $directPayload
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function snapshotFromDirectPayload(array $directPayload, array $envelope): array
    {
        $identification = data_get($directPayload, 'identificacao', []);
        $items = data_get($directPayload, 'itens', []);

        return $this->filterBlank([
            'ambiente_fiscal' => data_get($identification, 'ambiente', data_get($envelope, 'ambiente_fiscal')),
            'direcao_documento' => 'saida',
            'data_referencia' => data_get($identification, 'data_emissao'),
            'dados_documento' => $this->filterBlank([
                'serie' => data_get($identification, 'serie'),
                'numero' => data_get($identification, 'numero'),
                'data_emissao' => data_get($identification, 'data_emissao'),
                'natureza_operacao' => data_get($identification, 'natureza_operacao'),
                'valor_total' => data_get($directPayload, 'totais.valor_documento'),
            ]),
            'tomador' => $this->counterpartyFromCanonicalParty(data_get($directPayload, 'tomador', [])),
            'referencias_documento' => [],
            'itens' => is_array($items) ? $this->itemsFromCanonicalPayload($items) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function counterpartyFromCanonicalParty(mixed $party): array
    {
        if (! is_array($party) || $party === []) {
            return [
                'comprador_identificado' => false,
                'consumidor_final' => true,
                'indicador_ie' => '9',
            ];
        }

        $document = $this->digitsOrNull(data_get($party, 'cpf_cnpj'));

        return $this->filterBlank([
            'comprador_identificado' => $document !== null,
            'consumidor_final' => true,
            'indicador_ie' => '9',
            'nome' => data_get($party, 'razao_social'),
            'documento' => $document,
            'tipo_pessoa' => $this->normalizeOperationPersonType(data_get($party, 'tipo_pessoa')),
            'inscricao_estadual' => data_get($party, 'inscricao_estadual'),
            'email' => data_get($party, 'email'),
            'telefone' => data_get($party, 'telefone'),
            'logradouro' => data_get($party, 'endereco.logradouro'),
            'numero' => data_get($party, 'endereco.numero'),
            'complemento' => data_get($party, 'endereco.complemento'),
            'bairro' => data_get($party, 'endereco.bairro'),
            'municipio' => data_get($party, 'endereco.municipio'),
            'uf' => data_get($party, 'endereco.uf'),
            'cep' => data_get($party, 'endereco.cep'),
            'codigo_ibge' => data_get($party, 'endereco.codigo_ibge', data_get($party, 'endereco.codigo_municipio')),
        ]);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemsFromCanonicalPayload(array $items): array
    {
        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return $this->filterBlank([
                'codigo' => data_get($item, 'codigo'),
                'descricao' => data_get($item, 'descricao', 'Produto'),
                'tipo_item' => $this->normalizeOperationItemType(data_get($item, 'tipo_item', 'produto')),
                'origem_codigo' => data_get($item, 'origem_codigo', data_get($item, 'codigo_origem')),
                'quantidade' => data_get($item, 'quantidade'),
                'valor_unitario' => data_get($item, 'valor_unitario'),
                'valor_bruto' => data_get($item, 'valor_total'),
                'valor_desconto' => data_get($item, 'valor_desconto'),
                'valor_frete' => data_get($item, 'valor_frete'),
                'valor_seguro' => data_get($item, 'valor_seguro'),
                'valor_outras_despesas' => data_get($item, 'valor_outras_despesas', data_get($item, 'outros_valores')),
                'unidade' => data_get($item, 'unidade'),
                'ncm' => data_get($item, 'ncm'),
                'cfop' => data_get($item, 'cfop'),
            ]);
        }, $items)));
    }

    public function sync(SaleFiscalDocument $document, ?FiscalConfig $config = null, ?int $timeoutSeconds = null, array $debug = []): SaleFiscalDocument
    {
        $startedAt = microtime(true);
        $client = $this->client($config);
        $response = $timeoutSeconds === null
            ? $client->documentV2($document->external_id)
            : $client->waitDocumentV2($document->external_id, $timeoutSeconds);

        return $this->applyRemoteResponse($document, $this->withDebug($response, $debug + [
            'sync_ms' => $this->elapsedMs($startedAt),
            'sync_timeout_s' => $timeoutSeconds,
        ]));
    }

    private function syncAuthorizedArtifacts(SaleFiscalDocument $document, ?FiscalConfig $config = null, int $timeoutSeconds = 8): SaleFiscalDocument
    {
        if (! $this->artifactsStillProcessing($document)) {
            return $document;
        }

        $client = $this->client($config);
        $startedAt = microtime(true);
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        $polls = 0;

        do {
            usleep(500 * 1000);
            $polls++;

            $document = $this->applyRemoteResponse(
                $document,
                $this->withDebug($client->documentV2($document->external_id), [
                    'artifact_poll_count' => $polls,
                    'artifact_wait_ms' => $this->elapsedMs($startedAt),
                    'artifact_wait_timeout_s' => $timeoutSeconds,
                ]),
            );

            if ($document->status !== SaleFiscalDocument::STATUS_AUTHORIZED || ! $this->artifactsStillProcessing($document)) {
                return $document;
            }
        } while (microtime(true) < $deadline);

        return $document;
    }

    public function syncArtifacts(SaleFiscalDocument $document, ?FiscalConfig $config = null, ?int $timeoutSeconds = null): SaleFiscalDocument
    {
        return $this->syncAuthorizedArtifacts(
            $document,
            $config,
            max(1, $timeoutSeconds ?? (int) config('pdv.notagil.wait_seconds', 8)),
        );
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
        $response = $this->client($config)->cancelDocumentV2($document->external_id, $reason);

        $document = $this->applyRemoteResponse($document, $response);
        $document->forceFill([
            'status' => SaleFiscalDocument::STATUS_CANCELLED,
        ])->save();

        return $document;
    }

    public function download(SaleFiscalDocument $document, string $artifact, ?FiscalConfig $config = null): array
    {
        $filters = array_filter([
            'ambiente_fiscal' => $document->environment ?: $this->notagilEnvironment($config),
        ], static fn ($value): bool => $value !== null && $value !== '');

        return $artifact === 'xml'
            ? $this->client($config)->downloadDocumentXmlV2($document->external_id, $filters)
            : $this->client($config)->downloadDocumentPdfV2($document->external_id, $filters);
    }

    public function applyWebhookPayload(array $payload, ?string $externalId = null, array $debug = []): ?SaleFiscalDocument
    {
        $documentPayload = $this->resolveWebhookDocumentPayload($payload);
        $resolvedExternalId = trim((string) ($externalId ?: $this->firstFilled([
            data_get($documentPayload, 'external_id'),
            data_get($documentPayload, 'externalId'),
            data_get($documentPayload, 'document_external_id'),
            data_get($payload, 'external_id'),
            data_get($payload, 'externalId'),
            data_get($payload, 'document_external_id'),
            data_get($payload, 'document.external_id'),
            data_get($payload, 'data.external_id'),
            data_get($payload, 'data.document.external_id'),
        ])));

        if ($resolvedExternalId === '') {
            return null;
        }

        $document = SaleFiscalDocument::query()
            ->where('external_id', $resolvedExternalId)
            ->first();

        if (! $document) {
            return null;
        }

        return $this->applyRemoteResponse($document, $this->withDebug($documentPayload, [
            'webhook_received_at' => now()->toIso8601String(),
            ...$debug,
        ]));
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
                ?: data_get($error->payload, 'erros')
                ?: data_get($error->payload, 'message')
                ?: data_get($error->payload, 'mensagem')
                ?: data_get($error->payload, 'descricao')
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
        $items = collect($checkoutPayload['items'] ?? [])
            ->map(fn (array $item): array => $this->mapInputItem($item))
            ->values()
            ->all();
        $items = $this->aggregateFiscalItems($items);
        $metadata = $this->withIbptMetadata($config, $items, [
            'origem' => 'pdv',
            'operador_id' => $operatorId,
            'observacao_fiscal' => data_get($checkoutPayload, 'complementary.fiscal_observation')
                ?: data_get($checkoutPayload, 'complementary.observacao_nota'),
            'restaurante' => [
                'ficha_id' => data_get($checkoutPayload, 'complementary.restaurant_ficha_id'),
                'ficha_code' => data_get($checkoutPayload, 'complementary.restaurant_ficha_code'),
                'table_id' => data_get($checkoutPayload, 'complementary.restaurant_table_id'),
                'table_code' => data_get($checkoutPayload, 'complementary.restaurant_table_code'),
            ],
        ]);

        return $this->envelope(
            externalId: 'pdv-preview-'.(string) Str::uuid(),
            documentType: $documentType,
            config: $config,
            series: $series,
            number: $number,
            issuedAt: $issuedAt,
            total: (float) data_get($checkoutPayload, 'totals.payable_total', data_get($checkoutPayload, 'totals.net_total', 0)),
            counterparty: $this->counterparty($checkoutPayload['customer'] ?? null),
            items: $items,
            metadata: $metadata,
        );
    }

    private function buildSalePayload(Sale $sale, SaleFiscalDocument $document, FiscalConfig $config): array
    {
        $sale->loadMissing([
            'items.product',
            'items.catalogProduct.fiscalItemProfile',
            'items.catalogProduct.fiscalItemProfileSaida',
            'items.catalogProduct.unidadeMedida',
            'items.catalogProduct.codigosBarras',
            'items.catalogProduct.fiscalTags',
            'payments.paymentMethod',
        ]);

        $items = $sale->items
            ->map(fn ($item): array => $this->mapSaleItem($item))
            ->values()
            ->all();
        $items = $this->aggregateFiscalItems($items);
        $pafMetadata = array_filter([
            'dav_id' => $sale->paf_dav_id,
            'pre_venda_id' => $sale->paf_pre_sale_id,
            'requisicao_externa_id' => $sale->paf_external_requisition_id,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $paymentMetadata = $sale->payments
            ->map(fn ($payment): array => [
                'meio' => $payment->metodo_nome,
                'descricao' => $payment->descricao,
                'valor' => (float) $payment->valor,
                'intermediador_cnpj' => $payment->paymentMethod?->paf_intermediator_cnpj,
                'identificador_estabelecimento' => $payment->paymentMethod?->paf_intermediator_identifier,
                'tipo_documento_paf' => $payment->paf_document_type_code,
            ])
            ->filter(fn (array $payment): bool => $pafMetadata !== []
                || trim((string) ($payment['intermediador_cnpj'] ?? '')) !== ''
                || trim((string) ($payment['identificador_estabelecimento'] ?? '')) !== '')
            ->values()
            ->all();
        $metadataPayload = [
            'origem' => 'pdv',
            'venda_id' => $sale->id,
            'numero_venda' => $sale->numero,
            'operador_id' => $sale->created_by,
            'observacao_fiscal' => $sale->fiscal_observation,
        ];
        if ($paymentMetadata !== []) {
            $metadataPayload['pagamentos'] = $paymentMetadata;
        }
        if ($pafMetadata !== []) {
            $metadataPayload['paf'] = $pafMetadata;
        }
        $metadata = $this->withIbptMetadata($config, $items, $metadataPayload);

        return $this->envelope(
            externalId: $document->external_id,
            documentType: $document->document_type,
            config: $config,
            series: (string) $document->series,
            number: (int) $document->number,
            issuedAt: $sale->sold_at ?? now(),
            total: (float) $sale->total_financeiro,
            counterparty: $this->counterparty($this->saleCustomerPayload($sale)),
            items: $items,
            metadata: $metadata,
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
        $environment = $this->notagilEnvironment($config);
        $municipality = trim((string) (CompanySetting::query()->first()?->cidade ?? ''));

        return $this->operationPayload([
            'external_id' => $externalId,
            'document_type' => $documentType,
            'municipio' => $municipality !== '' ? $municipality : null,
            'metadados' => $metadata,
            'retrato' => [
                'fiscal_environment' => $environment,
                'document_direction' => 'saida',
                'reference_date' => $issuedAt->toIso8601String(),
                'document_data' => [
                    'serie' => $series,
                    'numero' => str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'data_emissao' => $issuedAt->toIso8601String(),
                    'natureza_operacao' => 'Venda de mercadoria',
                    'valor_total' => round($total, 2),
                ],
                'counterparty' => $this->normalizeOperationCounterparty($counterparty),
                'document_references' => [],
                'items' => $this->normalizeOperationItems($items),
            ],
        ], $this->shouldSubmitSynchronouslyForType($documentType, $config));
    }

    private function shouldSubmitSynchronouslyForType(string $documentType, ?FiscalConfig $config): bool
    {
        return (bool) ($config?->notagil_nfce_synchronous ?? false)
            && mb_strtolower($documentType) === 'nfce';
    }

    private function mapInputItem(array $item): array
    {
        $quantity = round((float) ($item['quantidade'] ?? 0), 3);
        $unitPrice = round((float) ($item['valor_unitario'] ?? 0), 2);
        $product = $this->resolveCatalogProduct($item['id'] ?? null);

        return $this->mapFiscalItem(
            productId: $product?->id,
            sku: (string) ($item['codigo_barras'] ?? $item['codigo'] ?? $this->resolveProductBarcode($product) ?? $product?->cod_sku ?? $product?->codigo_operacional ?? ''),
            description: (string) ($item['nome'] ?? $product?->descricao ?? 'Produto'),
            quantity: $quantity,
            unitPrice: $unitPrice,
            product: $product,
            fallbackUnit: $this->resolveInputUnit($item),
        );
    }

    private function mapSaleItem($item): array
    {
        $product = $item->catalogProduct ?: null;
        $legacyProduct = $item->product ?: null;

        return $this->mapFiscalItem(
            productId: $product?->id,
            sku: (string) ($item->produto_codigo ?? $this->resolveProductBarcode($product) ?? $product?->cod_sku ?? $product?->codigo_operacional ?? $legacyProduct?->codigo ?? ''),
            description: (string) ($item->produto_nome ?: $product?->descricao ?: 'Produto'),
            quantity: (float) $item->quantidade,
            unitPrice: (float) $item->valor_unitario,
            product: $product,
            fallbackUnit: (string) ($item->unidade ?: 'UN'),
            legacyProduct: $legacyProduct,
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
        ?Product $legacyProduct = null,
    ): array {
        $saidaProfile = $product?->fiscalItemProfileSaida;
        $defaultProfile = $product?->fiscalItemProfile;
        $profile = $saidaProfile ?: $defaultProfile;
        $attributes = is_array($product?->atributos_logisticos) ? $product->atributos_logisticos : [];
        $legacyAttributes = is_array($legacyProduct?->restaurant_config) ? $legacyProduct->restaurant_config : [];
        $isService = $this->isServiceFiscalItem($product);
        $ncm = $isService ? null : $this->resolveItemNcm($product?->ncm, $saidaProfile?->ncm, $defaultProfile?->ncm, data_get($attributes, 'fiscal_ncm'), $legacyAttributes);
        $fiscalTags = $product && $product->relationLoaded('fiscalTags')
            ? $product->fiscalTags->pluck('tag')->values()->all()
            : [];

        return array_filter([
            'product_id' => data_get($attributes, 'notagil_product_id'),
            'sku' => trim($sku) ?: null,
            'description' => trim($description) ?: 'Produto',
            'item_type' => $isService ? 'service' : 'product',
            'ncm' => $ncm,
            'cest' => $isService ? null : $this->digitsOrNull($this->firstFilled([$product?->cest, $profile?->cest])),
            'origin_code' => $isService ? null : (string) $this->firstFilled([$product?->origem_mercadoria, $profile?->origem_mercadoria, data_get($attributes, 'fiscal_origem', '0')]),
            'service_code' => $isService ? $this->firstFilled([$product?->servico_codigo, $profile?->servico_codigo]) : null,
            'codigo_nbs' => $isService ? $product?->codigo_nbs : null,
            'fiscal_tags' => $fiscalTags ?: null,
            'tax_classification_code' => $this->firstFilled([$product?->cod_classe_tributo, $profile?->cod_classe_tributo, data_get($attributes, 'fiscal_tax_classification_code')]),
            'unit' => $this->resolveFiscalUnit($product, $fallbackUnit),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => round($quantity * $unitPrice, 2),
            'discount_amount' => 0,
            'freight_amount' => 0,
            'insurance_amount' => 0,
            'other_amount' => 0,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function aggregateFiscalItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $key = $this->fiscalItemGroupKey($item);

            if (! isset($grouped[$key])) {
                $grouped[$key] = $item;

                continue;
            }

            $grouped[$key]['quantity'] = round((float) ($grouped[$key]['quantity'] ?? 0) + (float) ($item['quantity'] ?? 0), 3);

            foreach (['gross_amount', 'discount_amount', 'freight_amount', 'insurance_amount', 'other_amount'] as $field) {
                $grouped[$key][$field] = round((float) ($grouped[$key][$field] ?? 0) + (float) ($item[$field] ?? 0), 2);
            }
        }

        return array_values($grouped);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveInputUnit(array $item): string
    {
        foreach ([
            $item['unidade_tributavel'] ?? null,
            data_get($item, 'tributacao.unidade_tributavel'),
            data_get($item, 'restaurant_config.tributacao.unidade_tributavel'),
            data_get($item, 'unidade_medida.codigo_fiscal'),
            data_get($item, 'unidadeMedida.codigo_fiscal'),
            $item['unidade'] ?? null,
            $item['unit'] ?? null,
            data_get($item, 'unidade_medida.unidade'),
            data_get($item, 'unidadeMedida.unidade'),
            'UN',
        ] as $candidate) {
            $unit = $this->normalizeUnitCode($candidate);
            if ($unit !== '') {
                return $unit;
            }
        }

        return 'UN';
    }

    private function resolveFiscalUnit(?Produto $product, mixed $fallbackUnit): string
    {
        foreach ([
            $product?->unidadeMedida?->codigo_fiscal,
            $product?->unidadeMedida?->unidade,
            $fallbackUnit,
            'UN',
        ] as $candidate) {
            $unit = $this->normalizeUnitCode($candidate);
            if ($unit !== '') {
                return $unit;
            }
        }

        return 'UN';
    }

    private function normalizeUnitCode(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function withIbptMetadata(FiscalConfig $config, array $items, array $metadata): array
    {
        if (! $this->ibptLookupEnabled()) {
            return $metadata;
        }

        try {
            $request = $this->buildIbptCupomRequest($config, $items);

            if (($request['payload']['uf'] ?? '') === '') {
                return $this->handleIbptIssue($config, $metadata, 'UF da empresa emissora não configurada para consulta IBPT.');
            }

            if (($request['missing_count'] ?? 0) > 0) {
                $message = 'Há itens fiscais sem NCM válido para consulta IBPT.';
                if ($this->isProductionEnvironment($config)) {
                    throw new NotaAgilConfigurationException($message);
                }

                $metadata = $this->mergeMetadataDebug($metadata, [
                    'ibpt_missing_items' => $request['missing_indexes'] ?? [],
                ]);
            }

            if (count($request['payload']['itens'] ?? []) === 0) {
                return $this->handleIbptIssue($config, $metadata, 'Nenhum item fiscal elegível para consulta IBPT.');
            }

            $ibpt = $this->normalizeIbptCupomResponse(
                $this->resolveIbptCupom($config, $request['payload']),
            );

            if (! $this->ibptResponseCoversRequest($ibpt, count($request['payload']['itens']))) {
                return $this->handleIbptIssue($config, $metadata, 'Resposta IBPT incompleta para os itens fiscais do cupom.');
            }

            $message = $this->formatIbptMessage($ibpt);
            if ($message === '') {
                return $this->handleIbptIssue($config, $metadata, 'Resposta IBPT sem totais tributários para o cupom.');
            }

            $currentObservation = $metadata['observacao_fiscal'] ?? $metadata['fiscal_observation'] ?? null;

            return [
                ...$metadata,
                'observacao_fiscal' => $this->appendFiscalObservation($currentObservation, $message),
                'ibpt' => $ibpt,
                'mensagem_ibpt' => $message,
            ];
        } catch (Throwable $error) {
            if ($this->isProductionEnvironment($config)) {
                throw $error instanceof NotaAgilConfigurationException
                    ? $error
                    : new NotaAgilConfigurationException('Não foi possível resolver os dados IBPT da NFC-e: '.$this->exceptionMessage($error), previous: $error);
            }

            return $this->mergeMetadataDebug($metadata, [
                'ibpt_error' => $this->exceptionMessage($error),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{payload: array{uf: string, itens: array<int, array<string, mixed>>}, missing_count: int, missing_indexes: array<int, int>}
     */
    private function buildIbptCupomRequest(FiscalConfig $config, array $items): array
    {
        $payloadItems = [];
        $missingIndexes = [];

        foreach ($items as $index => $item) {
            $ncm = $this->digitsOrNull($item['ncm'] ?? null);
            if ($ncm === null || strlen($ncm) !== 8) {
                $missingIndexes[] = $index + 1;

                continue;
            }

            $originCode = trim((string) ($item['origin_code'] ?? ''));
            $payloadItem = $this->filterBlank([
                'ncm' => $ncm,
                'valor' => $this->ibptItemValue($item),
                'descricao' => $item['description'] ?? null,
                'unidade' => $item['unit'] ?? null,
                'codigo_interno' => $item['sku'] ?? $item['external_product_id'] ?? null,
            ]);

            if ($originCode !== '') {
                $payloadItem['codigo_origem'] = $originCode;
            }

            if ($this->isImportedOrigin($originCode)) {
                $payloadItem['importado'] = true;
                $payloadItem['tipo_mercadoria'] = 'importada';
            }

            $payloadItems[] = $payloadItem;
        }

        return [
            'payload' => [
                'uf' => $this->resolveIbptUf($config),
                'itens' => $payloadItems,
            ],
            'missing_count' => count($missingIndexes),
            'missing_indexes' => $missingIndexes,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveIbptCupom(FiscalConfig $config, array $payload): array
    {
        if ($this->token($config) === '') {
            throw new NotaAgilConfigurationException('Configure o token NotaAgil antes de consultar IBPT.');
        }

        return $this->client($config)->consultIbptCouponV2($payload);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeIbptCupomResponse(array $response): array
    {
        $dados = data_get($response, 'dados');
        if (! is_array($dados)) {
            $dados = data_get($response, 'data.dados');
        }
        if (! is_array($dados)) {
            $dados = [];
        }

        $itens = data_get($dados, 'itens', []);
        $totais = data_get($dados, 'totais', []);
        $cache = data_get($dados, 'cache', []);
        $metadados = data_get($response, 'metadados', data_get($response, 'data.metadados', []));

        return [
            'dados' => [
                'itens' => is_array($itens) ? array_values($itens) : [],
                'totais' => is_array($totais) ? $totais : [],
                'cache' => is_array($cache) ? $cache : [],
            ],
            'metadados' => is_array($metadados) ? $metadados : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $ibpt
     */
    private function ibptResponseCoversRequest(array $ibpt, int $requestedItems): bool
    {
        $items = data_get($ibpt, 'dados.itens', []);
        $totals = data_get($ibpt, 'dados.totais', []);

        return is_array($items)
            && count($items) >= $requestedItems
            && is_array($totals)
            && $this->numberOrNull(data_get($totals, 'tributo_total')) !== null;
    }

    /**
     * @param  array<string, mixed>  $ibpt
     */
    private function formatIbptMessage(array $ibpt): string
    {
        $totals = data_get($ibpt, 'dados.totais', []);
        if (! is_array($totals)) {
            return '';
        }

        $total = $this->numberOrNull(data_get($totals, 'tributo_total'));
        if ($total === null) {
            return '';
        }

        $federal = $this->numberOrNull(data_get($totals, 'tributo_federal')) ?? 0.0;
        $estadual = $this->numberOrNull(data_get($totals, 'tributo_estadual')) ?? 0.0;
        $municipal = $this->numberOrNull(data_get($totals, 'tributo_municipal')) ?? 0.0;
        $table = $this->firstIbptTable($ibpt);
        $sourceParts = array_filter([
            data_get($table, 'fonte') ?: 'IBPT',
            data_get($table, 'versao'),
            data_get($table, 'chave'),
        ], static fn ($value): bool => trim((string) $value) !== '');

        return sprintf(
            'Total aproximado de tributos R$ %s: Federal R$ %s, Estadual R$ %s, Municipal R$ %s. Fonte: %s.',
            $this->formatIbptMoney($total),
            $this->formatIbptMoney($federal),
            $this->formatIbptMoney($estadual),
            $this->formatIbptMoney($municipal),
            implode(' ', $sourceParts),
        );
    }

    /**
     * @param  array<string, mixed>  $ibpt
     * @return array<string, mixed>
     */
    private function firstIbptTable(array $ibpt): array
    {
        foreach (data_get($ibpt, 'dados.itens', []) as $item) {
            $table = data_get($item, 'tabela');
            if (is_array($table) && $table !== []) {
                return $table;
            }
        }

        return [];
    }

    private function handleIbptIssue(FiscalConfig $config, array $metadata, string $message): array
    {
        if ($this->isProductionEnvironment($config)) {
            throw new NotaAgilConfigurationException($message);
        }

        return $this->mergeMetadataDebug($metadata, [
            'ibpt_error' => $message,
            'ibpt_skipped' => true,
        ]);
    }

    private function mergeMetadataDebug(array $metadata, array $debug): array
    {
        $current = data_get($metadata, '_debug');
        $metadata['_debug'] = array_filter([
            ...(is_array($current) ? $current : []),
            ...$debug,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function ibptItemValue(array $item): float
    {
        return round(
            (float) ($item['gross_amount'] ?? 0)
            - (float) ($item['discount_amount'] ?? 0)
            + (float) ($item['freight_amount'] ?? 0)
            + (float) ($item['insurance_amount'] ?? 0)
            + (float) ($item['other_amount'] ?? 0),
            2,
        );
    }

    protected function resolveIbptUf(FiscalConfig $config): string
    {
        $company = CompanySetting::query()->first();
        $uf = mb_strtoupper(trim((string) ($company?->uf ?? '')));

        return preg_match('/^[A-Z]{2}$/', $uf) === 1 ? $uf : '';
    }

    private function appendFiscalObservation(mixed $current, string $message): string
    {
        $current = trim((string) $current);
        if ($current === '') {
            return $message;
        }

        if (str_contains($current, $message)) {
            return $current;
        }

        return $current.' '.$message;
    }

    private function isProductionEnvironment(FiscalConfig $config): bool
    {
        $environment = strtr(mb_strtolower(trim((string) $config->ambiente)), [
            'ç' => 'c',
            'ã' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'ú' => 'u',
        ]);

        return in_array($environment, ['producao', 'production', 'prod'], true);
    }

    private function notagilEnvironment(?FiscalConfig $config, bool $allowAll = false): string
    {
        $environment = strtr(mb_strtolower(trim((string) ($config?->ambiente ?? ''))), [
            'ç' => 'c',
            'ã' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'ú' => 'u',
        ]);

        if (in_array($environment, ['producao', 'production', 'prod'], true)) {
            return 'producao';
        }

        if ($allowAll && in_array($environment, ['', 'todos', 'all'], true)) {
            return 'todos';
        }

        return 'homologacao';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterBlank(array $payload): array
    {
        return array_filter($payload, static fn ($value): bool => $value !== null && $value !== '');
    }

    private function isImportedOrigin(string $originCode): bool
    {
        return in_array($originCode, ['1', '2', '3', '6', '7', '8'], true);
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        $number = (float) $normalized;

        return is_numeric($normalized) ? $number : null;
    }

    private function formatIbptMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function fiscalItemGroupKey(array $item): string
    {
        $comparable = array_diff_key($item, array_flip([
            'quantity',
            'gross_amount',
            'discount_amount',
            'freight_amount',
            'insurance_amount',
            'other_amount',
        ]));
        ksort($comparable);

        return hash('sha256', json_encode($comparable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($comparable));
    }

    private function resolveItemNcm(mixed $productNcm, mixed $saidaProfileNcm, mixed $defaultProfileNcm, mixed $attributeNcm, array $legacyAttributes): ?string
    {
        foreach ([
            $productNcm,
            $saidaProfileNcm,
            $defaultProfileNcm,
            $attributeNcm,
            data_get($legacyAttributes, 'tributacao.ncm'),
            data_get($legacyAttributes, 'fiscal_ncm'),
        ] as $candidate) {
            $digits = $this->digitsOrNull($candidate);
            if ($digits !== null) {
                return $digits;
            }
        }

        return null;
    }

    private function isServiceFiscalItem(?Produto $product): bool
    {
        if (! $product) {
            return false;
        }

        return mb_strtoupper(trim((string) $product->produto_tipo)) === 'SERVICO'
            || trim((string) $product->tipo_item) === '09'
            || mb_strtoupper(trim((string) $product->natureza_item)) === 'SERVICO';
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function resolveProductBarcode(?Produto $product): ?string
    {
        if (! $product) {
            return null;
        }

        $barcode = $product->relationLoaded('codigosBarras')
            ? ($product->codigosBarras->firstWhere('principal', true) ?? $product->codigosBarras->first())
            : $product->codigosBarras()->where('ativo', true)->orderByDesc('principal')->first();

        $code = trim((string) ($barcode?->codigo ?? ''));

        return $code !== '' ? $code : null;
    }

    private function resolveCatalogProduct(mixed $id): ?Produto
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        return Produto::query()
            ->with(['fiscalItemProfile', 'fiscalItemProfileSaida', 'unidadeMedida', 'codigosBarras', 'fiscalTags'])
            ->find($id);
    }

    private function counterparty(mixed $customer): array
    {
        $customer = is_array($customer) ? $customer : [];
        $document = preg_replace('/\D+/', '', (string) $this->firstFilled([
            data_get($customer, 'cpf_cnpj'),
            data_get($customer, 'cpfCnpj'),
            data_get($customer, 'documento'),
            data_get($customer, 'document'),
            data_get($customer, 'cnpj'),
            data_get($customer, 'cpf'),
        ]));
        $name = trim((string) data_get($customer, 'nome', ''));
        $personType = $this->personType($customer, $document);

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
            'person_type' => $personType,
            'email' => data_get($customer, 'email'),
            'telefone' => data_get($customer, 'telefone') ?: data_get($customer, 'phone'),
            'uf' => data_get($customer, 'uf') ?: data_get($customer, 'state'),
            'municipio' => data_get($customer, 'municipio') ?: data_get($customer, 'cidade') ?: data_get($customer, 'city'),
            'codigo_ibge' => data_get($customer, 'codigo_ibge'),
            'cep' => preg_replace('/\D+/', '', (string) data_get($customer, 'cep', '')) ?: null,
            'logradouro' => data_get($customer, 'logradouro') ?: data_get($customer, 'street'),
            'numero' => data_get($customer, 'numero') ?: data_get($customer, 'number'),
            'bairro' => data_get($customer, 'bairro') ?: data_get($customer, 'neighborhood'),
            'complemento' => data_get($customer, 'complemento') ?: data_get($customer, 'complement'),
            'indicador_ie' => data_get($customer, 'indicador_ie', '9'),
            'inscricao_estadual' => data_get($customer, 'inscricao_estadual'),
            'country_code' => data_get($customer, 'country_code') ?: (data_get($customer, 'pais') || data_get($customer, 'country') ? '1058' : null),
            'final_consumer' => true,
            'buyer_identified' => $document !== '',
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function personType(array $customer, string $document): ?string
    {
        $type = mb_strtolower(trim((string) (data_get($customer, 'person_type') ?: data_get($customer, 'tipo_pessoa') ?: data_get($customer, 'personType'))));
        $type = strtr($type, ['í' => 'i', 'í' => 'i', 'é' => 'e', 'ã' => 'a', 'á' => 'a']);

        if (in_array($type, ['pf', 'fisica', 'pessoa_fisica', 'pessoa fisica'], true)) {
            return 'pf';
        }

        if (in_array($type, ['pj', 'juridica', 'pessoa_juridica', 'pessoa juridica'], true)) {
            return 'pj';
        }

        return $document !== '' ? (strlen($document) > 11 ? 'pj' : 'pf') : null;
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

    private function saleCustomerPayload(Sale $sale): array
    {
        $snapshot = is_array($sale->customer_snapshot ?? null) ? $sale->customer_snapshot : [];

        if (! $snapshot) {
            return ['nome' => $sale->cliente_nome];
        }

        if (! data_get($snapshot, 'nome') && $sale->cliente_nome) {
            $snapshot['nome'] = $sale->cliente_nome;
        }

        return $snapshot;
    }

    private function applyRemoteResponse(SaleFiscalDocument $document, array $response): SaleFiscalDocument
    {
        $response = NotaAgilClient::normalizeDocumentResponse($response);
        $fiscalStatus = (string) data_get($response, 'fiscal_status', data_get($response, 'status_fiscal', data_get($response, 'status', '')));
        $operationalStatus = (string) data_get($response, 'operational_status', data_get($response, 'status_operacional', data_get($response, 'notagil_event_type', '')));
        $webhookType = (string) data_get($response, 'webhook_type', data_get($response, 'type', ''));
        $status = $this->normalizeStatus($fiscalStatus, $operationalStatus, $webhookType, $document->status);
        $lastError = $this->resolveLastError($response, $status, $document->last_error);

        $document->forceFill([
            'status' => $status,
            'fiscal_status' => $fiscalStatus ?: $document->fiscal_status,
            'operational_status' => $operationalStatus ?: $document->operational_status,
            'access_key' => data_get($response, 'access_key', data_get($response, 'document_key', data_get($response, 'chave_documento', data_get($response, 'chave_acesso', $document->access_key)))),
            'protocol' => data_get($response, 'protocol', data_get($response, 'protocolo', $document->protocol)),
            'authorized_at' => $this->resolveAuthorizedAt($response, $status) ?: $document->authorized_at,
            'response_payload' => $response,
            'last_error' => $lastError,
            'next_retry_at' => $status === SaleFiscalDocument::STATUS_PROCESSING ? now()->addMinute() : null,
        ])->save();

        return $document;
    }

    private function resolveWebhookDocumentPayload(array $payload): array
    {
        $v2Document = data_get($payload, 'dados.documento')
            ?: data_get($payload, 'dados.document')
            ?: data_get($payload, 'documento');
        if (is_array($v2Document)) {
            return array_filter([
                ...$v2Document,
                'webhook_id' => data_get($payload, 'id') ?: data_get($payload, 'notificacao_web_id'),
                'webhook_type' => $this->resolveWebhookType($payload),
                'notagil_event_id' => data_get($payload, 'dados.evento.id') ?: data_get($payload, 'evento.id'),
                'notagil_event_type' => data_get($payload, 'nome_evento') ?: data_get($payload, 'dados.evento.nome_evento'),
                'notagil_event_occurred_at' => data_get($payload, 'criado_em') ?: data_get($payload, 'dados.evento.ocorrido_em'),
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        $document = data_get($payload, 'data.document');
        if (is_array($document)) {
            $eventType = $this->resolveWebhookType($payload);
            $notagilEventType = data_get($payload, 'data.event.event_type');

            if (! data_get($document, 'operational_status') && $notagilEventType) {
                $document['operational_status'] = $notagilEventType;
            }

            if (! data_get($document, 'authorized_at') && $eventType === 'fiscal_document.authorized') {
                $document['authorized_at'] = data_get($payload, 'data.event.occurred_at')
                    ?: data_get($document, 'updated_at')
                    ?: data_get($payload, 'created_at');
            }

            return array_filter([
                ...$document,
                'webhook_id' => data_get($payload, 'id'),
                'webhook_type' => $eventType,
                'notagil_event_id' => data_get($payload, 'data.event.id'),
                'notagil_event_type' => $notagilEventType,
                'notagil_event_occurred_at' => data_get($payload, 'data.event.occurred_at'),
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        foreach (['document', 'data.document', 'data', 'payload.document', 'payload'] as $key) {
            $value = data_get($payload, $key);

            if (is_array($value) && (
                data_get($value, 'fiscal_status') !== null
                || data_get($value, 'status_fiscal') !== null
                || data_get($value, 'status') !== null
                || data_get($value, 'operational_status') !== null
                || data_get($value, 'status_operacional') !== null
            )) {
                return $value;
            }
        }

        return $payload;
    }

    private function resolveWebhookType(array $payload): string
    {
        return mb_strtolower(trim((string) $this->firstFilled([
            data_get($payload, 'nome_evento'),
            data_get($payload, 'dados.evento.nome_evento'),
            data_get($payload, 'type'),
            data_get($payload, 'event.type'),
            data_get($payload, 'event'),
            data_get($payload, 'webhook_type'),
        ])));
    }

    private function withDebug(array $response, array $debug): array
    {
        $current = data_get($response, '_debug');
        $response['_debug'] = array_filter([
            ...(is_array($current) ? $current : []),
            ...$debug,
            'captured_at' => now()->toIso8601String(),
        ], static fn ($value): bool => $value !== null && $value !== '');

        return $response;
    }

    private function mergeDebug(SaleFiscalDocument $document, array $debug): SaleFiscalDocument
    {
        $payload = is_array($document->response_payload) ? $document->response_payload : [];
        $payload = $this->withDebug($payload, $debug);
        $document->forceFill([
            'response_payload' => $payload,
        ])->save();

        return $document;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function normalizeStatus(string $fiscalStatus, string $operationalStatus, string $webhookType = '', ?string $currentStatus = null): string
    {
        $fiscal = mb_strtolower($fiscalStatus);
        $operational = mb_strtolower($operationalStatus);
        $event = mb_strtolower($webhookType);

        if (
            in_array($event, ['fiscal_document.rejected'], true)
            || in_array($fiscal, ['rejected', 'rejeitada', 'rejeitado', 'denied', 'denegada', 'denegado'], true)
        ) {
            return SaleFiscalDocument::STATUS_REJECTED;
        }

        if (
            in_array($event, ['fiscal_document.cancelled'], true)
            || in_array($fiscal, ['cancelled', 'canceled', 'cancelada', 'cancelado'], true)
            || in_array($operational, ['cancelled', 'canceled', 'cancelada', 'cancelado'], true)
        ) {
            return SaleFiscalDocument::STATUS_CANCELLED;
        }

        if (
            in_array($event, ['fiscal_document.corrected'], true)
            || in_array($operational, ['carta_correcao_emitida'], true)
        ) {
            return in_array($currentStatus, [
                SaleFiscalDocument::STATUS_AUTHORIZED,
                SaleFiscalDocument::STATUS_REJECTED,
                SaleFiscalDocument::STATUS_CANCELLED,
            ], true) ? $currentStatus : SaleFiscalDocument::STATUS_AUTHORIZED;
        }

        if (
            in_array($event, ['fiscal_document.failed'], true)
            || in_array($operational, ['failed', 'falhou', 'retry_exhausted', 'tentativas_esgotadas'], true)
        ) {
            return SaleFiscalDocument::STATUS_CONTINGENCY_PENDING;
        }

        if (
            in_array($event, ['fiscal_document.authorized', 'nota_emitida'], true)
            || in_array($fiscal, ['authorized', 'autorizada', 'autorizado'], true)
            || in_array($operational, ['completed', 'concluido', 'concluida', 'nota_emitida'], true)
        ) {
            return SaleFiscalDocument::STATUS_AUTHORIZED;
        }

        return SaleFiscalDocument::STATUS_PROCESSING;
    }

    private function resolveAuthorizedAt(array $response, string $status): ?Carbon
    {
        $value = data_get($response, 'authorized_at')
            ?: data_get($response, 'autorizado_em')
            ?: data_get($response, 'autorizada_em')
            ?: data_get($response, 'notagil_event_occurred_at');
        if ($value) {
            return Carbon::parse($value);
        }

        return $status === SaleFiscalDocument::STATUS_AUTHORIZED ? now() : null;
    }

    private function resolveLastError(array $response, string $status, ?string $currentError): ?string
    {
        if (! in_array($status, [SaleFiscalDocument::STATUS_REJECTED, SaleFiscalDocument::STATUS_CONTINGENCY_PENDING], true)) {
            return null;
        }

        $message = $this->firstFilled([
            data_get($response, 'rejection_reason'),
            data_get($response, 'motivo_rejeicao'),
            data_get($response, 'last_error'),
            data_get($response, 'ultimo_erro'),
            data_get($response, 'message'),
            data_get($response, 'mensagem'),
            data_get($response, 'descricao'),
            data_get($response, 'error'),
            $currentError,
        ]);

        if ($message) {
            return (string) $message;
        }

        return $status === SaleFiscalDocument::STATUS_REJECTED
            ? 'Documento fiscal rejeitado.'
            : 'Falha operacional na emissão fiscal.';
    }

    private function artifactsStillProcessing(SaleFiscalDocument $document): bool
    {
        $artifacts = data_get($document->response_payload, 'artifacts');
        if (! is_array($artifacts) || $artifacts === []) {
            return false;
        }

        $xmlStatus = data_get($artifacts, 'xml_status');
        if ($xmlStatus) {
            return $xmlStatus === 'processing';
        }

        return (bool) data_get($artifacts, 'processing', false)
            && data_get($artifacts, 'xml_available', true) === false;
    }

    private function normalizeWebhookResponse(array $response, array $fallback): array
    {
        $endpoint = data_get($response, 'dados');
        if (! is_array($endpoint)) {
            $endpoint = data_get($response, 'data');
        }
        if (! is_array($endpoint)) {
            $endpoint = data_get($response, 'webhook');
        }
        if (! is_array($endpoint)) {
            $endpoint = $response;
        }

        $events = data_get($endpoint, 'eventos', data_get($endpoint, 'events', data_get($fallback, 'eventos', data_get($fallback, 'events', self::WEBHOOK_EVENTS))));
        $status = (string) data_get($endpoint, 'situacao', data_get($endpoint, 'status', data_get($fallback, 'situacao', data_get($fallback, 'status', 'ativo'))));

        return [
            'id' => (string) data_get($endpoint, 'id', data_get($fallback, 'id', '')),
            'url' => (string) data_get($endpoint, 'url', data_get($fallback, 'url')),
            'status' => $status === 'ativo' ? 'active' : ($status === 'pausado' ? 'paused' : $status),
            'environment' => data_get($endpoint, 'ambiente', data_get($endpoint, 'environment', data_get($fallback, 'ambiente', data_get($fallback, 'environment')))),
            'events' => is_array($events) ? array_values($events) : self::WEBHOOK_EVENTS,
            'secret' => (string) data_get($endpoint, 'segredo', data_get($endpoint, 'secret', data_get($response, 'segredo', data_get($response, 'secret', '')))),
        ];
    }

    private function token(?FiscalConfig $config = null): string
    {
        return trim((string) ($config?->notagil_token ?: config('pdv.notagil.token')));
    }

    private function ibptLookupEnabled(): bool
    {
        try {
            return (bool) config('pdv.notagil.ibpt_lookup_enabled', false);
        } catch (Throwable) {
            return false;
        }
    }

    protected function client(?FiscalConfig $config = null): NotaAgilClient
    {
        return new NotaAgilClient(
            baseUrl: $this->baseUrl($config),
            token: $this->token($config),
            http: new Client(['timeout' => max(1, (int) config('pdv.notagil.timeout', 30))]),
        );
    }

    private function baseUrl(?FiscalConfig $config = null): string
    {
        $baseUrl = rtrim((string) ($config?->notagil_base_url ?: config('pdv.notagil.base_url', 'https://notagil_api.vora-sys.com/api/v2/integrations')), '/');
        $path = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        return $path === '' ? $baseUrl.'/api/v2/integrations' : $baseUrl;
    }
}

class NotaAgilConfigurationException extends \RuntimeException {}
