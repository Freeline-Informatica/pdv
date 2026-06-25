<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\FiscalConfigProvider;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\DigitalCertificate;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Services\NotaAgilConfigurationException;
use Freeline\Pdv\Services\NotaAgilFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        private readonly CompanyContextResolver $companyContext,
        private readonly FiscalConfigProvider $fiscalConfig,
        private readonly NotaAgilFiscalService $notaAgil,
    ) {
    }

    public function company(): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json(array_merge(
                $this->companyContext->current() ?? [],
                ['managed_by_erp' => true],
            ));
        }

        $record = CompanySetting::query()->first();

        if ($record && blank($record->pdv_layout_mode)) {
            $record->pdv_layout_mode = 'varejo';
            $record->save();
        }

        return response()->json($record);
    }

    public function upsertCompany(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('empresa');
        }

        $payload = $request->validate([
            'cnpj' => ['nullable', 'string', 'max:18'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
            'inscricao_municipal' => ['nullable', 'string', 'max:255'],
            'regime_tributario' => ['nullable', 'string', 'max:255'],
            'cnae' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'cep' => ['nullable', 'string', 'max:10'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:30'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
            'pdv_layout_mode' => ['nullable', Rule::in(['varejo', 'restaurante', 'servicos'])],
        ]);

        $record = CompanySetting::query()->first() ?? new CompanySetting();
        if (blank($payload['pdv_layout_mode'] ?? null)) {
            $payload['pdv_layout_mode'] = $record->pdv_layout_mode ?: 'varejo';
        }
        $record->fill($payload)->save();

        return response()->json($record);
    }

    public function fiscal(): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json($this->presentFiscalPayload($this->fiscalConfig->current() ?? []));
        }

        return response()->json($this->presentFiscalConfig(FiscalConfig::query()->first()));
    }

    public function upsertFiscal(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('fiscal');
        }

        $payload = $this->validateFiscalPayload($request);

        $record = FiscalConfig::query()->first() ?? new FiscalConfig();
        if (array_key_exists('notagil_base_url', $payload)) {
            $payload['notagil_base_url'] = filled($payload['notagil_base_url'])
                ? rtrim((string) $payload['notagil_base_url'], '/')
                : null;
        }
        if (blank($payload['notagil_token'] ?? null)) {
            unset($payload['notagil_token']);
        }
        if (blank($payload['notagil_webhook_secret'] ?? null)) {
            unset($payload['notagil_webhook_secret']);
        }
        if (array_key_exists('layout_cupom', $payload)) {
            $payload['layout_cupom'] = $this->normalizeCupomLayoutPayload($payload['layout_cupom']);
        }

        $record->fill($payload)->save();

        return response()->json($this->presentFiscalConfig($record));
    }

    public function provisionNotagilWebhook(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('fiscal');
        }

        $payload = $this->validateFiscalPayload($request);
        if (array_key_exists('notagil_base_url', $payload)) {
            $payload['notagil_base_url'] = filled($payload['notagil_base_url'])
                ? rtrim((string) $payload['notagil_base_url'], '/')
                : null;
        }
        if (blank($payload['notagil_token'] ?? null)) {
            unset($payload['notagil_token']);
        }
        if (blank($payload['notagil_webhook_secret'] ?? null)) {
            unset($payload['notagil_webhook_secret']);
        }
        if (array_key_exists('layout_cupom', $payload)) {
            $payload['layout_cupom'] = $this->normalizeCupomLayoutPayload($payload['layout_cupom']);
        }
        if (blank($payload['notagil_webhook_url'] ?? null)) {
            $payload['notagil_webhook_url'] = $this->defaultNotagilWebhookUrl();
        }

        $record = FiscalConfig::query()->first() ?? new FiscalConfig();
        $record->fill($payload)->save();

        try {
            $webhook = $this->notaAgil->provisionWebhook($record, (string) $record->notagil_webhook_url);
        } catch (NotaAgilConfigurationException $exception) {
            $record->forceFill(['notagil_webhook_last_error' => $exception->getMessage()])->save();

            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);
            $record->forceFill(['notagil_webhook_last_error' => $exception->getMessage()])->save();

            return response()->json([
                'message' => 'Não foi possível criar ou atualizar o webhook no NotaAgil.',
            ], 502);
        }

        $updates = array_filter([
            'notagil_webhook_id' => data_get($webhook, 'id') ?: $record->notagil_webhook_id,
            'notagil_webhook_url' => data_get($webhook, 'url') ?: $record->notagil_webhook_url,
            'notagil_webhook_status' => data_get($webhook, 'status') ?: $record->notagil_webhook_status,
            'notagil_webhook_secret' => data_get($webhook, 'secret') ?: $record->notagil_webhook_secret,
            'notagil_webhook_last_synced_at' => now(),
        ], static fn ($value): bool => $value !== null && $value !== '');
        $updates['notagil_webhook_last_error'] = null;

        $record->forceFill($updates)->save();

        return response()->json([
            'message' => 'Webhook NotaAgil sincronizado.',
            'fiscal' => $this->presentFiscalConfig($record->fresh()),
            'webhook' => [
                'id' => data_get($webhook, 'id'),
                'url' => data_get($webhook, 'url'),
                'status' => data_get($webhook, 'status'),
                'events' => data_get($webhook, 'events', NotaAgilFiscalService::WEBHOOK_EVENTS),
            ],
        ]);
    }

    public function rotateNotagilWebhookSecret(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('fiscal');
        }

        $payload = $this->validateFiscalPayload($request);
        if (array_key_exists('notagil_base_url', $payload)) {
            $payload['notagil_base_url'] = filled($payload['notagil_base_url'])
                ? rtrim((string) $payload['notagil_base_url'], '/')
                : null;
        }
        if (blank($payload['notagil_token'] ?? null)) {
            unset($payload['notagil_token']);
        }
        if (blank($payload['notagil_webhook_secret'] ?? null)) {
            unset($payload['notagil_webhook_secret']);
        }
        if (array_key_exists('layout_cupom', $payload)) {
            $payload['layout_cupom'] = $this->normalizeCupomLayoutPayload($payload['layout_cupom']);
        }
        if (blank($payload['notagil_webhook_url'] ?? null)) {
            $payload['notagil_webhook_url'] = $this->defaultNotagilWebhookUrl();
        }

        $record = FiscalConfig::query()->first() ?? new FiscalConfig();
        $record->fill($payload)->save();

        try {
            $webhook = $this->notaAgil->rotateWebhookSecret($record);
        } catch (NotaAgilConfigurationException $exception) {
            $record->forceFill(['notagil_webhook_last_error' => $exception->getMessage()])->save();

            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);
            $record->forceFill(['notagil_webhook_last_error' => $exception->getMessage()])->save();

            return response()->json([
                'message' => 'Não foi possível rotacionar o segredo do webhook no NotaAgil.',
            ], 502);
        }

        $secret = trim((string) data_get($webhook, 'secret'));
        if ($secret === '') {
            $record->forceFill(['notagil_webhook_last_error' => 'O NotaAgil não retornou o novo segredo do webhook.'])->save();

            return response()->json([
                'message' => 'O NotaAgil não retornou o novo segredo do webhook.',
            ], 502);
        }

        $updates = array_filter([
            'notagil_webhook_id' => data_get($webhook, 'id') ?: $record->notagil_webhook_id,
            'notagil_webhook_url' => data_get($webhook, 'url') ?: $record->notagil_webhook_url,
            'notagil_webhook_status' => data_get($webhook, 'status') ?: $record->notagil_webhook_status,
            'notagil_webhook_secret' => $secret,
            'notagil_webhook_last_synced_at' => now(),
        ], static fn ($value): bool => $value !== null && $value !== '');
        $updates['notagil_webhook_last_error'] = null;

        $record->forceFill($updates)->save();

        return response()->json([
            'message' => 'Segredo do webhook NotaAgil rotacionado.',
            'fiscal' => $this->presentFiscalConfig($record->fresh()),
            'webhook' => [
                'id' => data_get($webhook, 'id'),
                'url' => data_get($webhook, 'url'),
                'status' => data_get($webhook, 'status'),
                'events' => data_get($webhook, 'events', NotaAgilFiscalService::WEBHOOK_EVENTS),
            ],
        ]);
    }

    public function certificate(): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            $certificate = data_get($this->fiscalConfig->current(), 'certificate');
            if (is_array($certificate)) {
                $certificate['managed_by_platform'] = $certificate['managed_by_platform'] ?? true;
                $certificate['source'] = $certificate['source'] ?? 'notagil';
            }

            return response()->json([
                'certificate' => $certificate,
            ]);
        }

        return response()->json(DigitalCertificate::query()->first());
    }

    public function upsertCertificate(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('certificado');
        }

        $payload = $request->validate([
            'tipo' => ['required', 'string', 'max:10'],
            'validade' => ['nullable', 'date'],
            'arquivo_nome' => ['nullable', 'string', 'max:255'],
            'senha_hash' => ['nullable', 'string', 'max:255'],
        ]);

        $record = DigitalCertificate::query()->first() ?? new DigitalCertificate();
        $record->fill($payload)->save();

        return response()->json($record);
    }

    private function erpManagedResponse(string $resource): JsonResponse
    {
        return response()->json([
            'message' => "A configuração de {$resource} é gerenciada pelo ERP.",
        ], 409);
    }

    private function presentFiscalConfig(?FiscalConfig $record): ?array
    {
        if (! $record) {
            return null;
        }

        return $this->presentFiscalPayload($record->toArray(), $record);
    }

    private function presentFiscalPayload(array $payload, ?FiscalConfig $record = null): array
    {
        $dbToken = trim((string) ($record?->notagil_token ?? data_get($payload, 'notagil_token', '')));
        $envToken = trim((string) config('pdv.notagil.token'));
        $dbSecret = trim((string) ($record?->notagil_webhook_secret ?? data_get($payload, 'notagil_webhook_secret', '')));
        $envSecret = trim((string) config('pdv.notagil.webhook_secret'));
        $payload['notagil_token'] = '';
        $payload['notagil_webhook_secret'] = '';

        $payload['notagil_webhook_url'] = data_get($payload, 'notagil_webhook_url')
            ?: config('pdv.notagil.webhook_url')
            ?: url('/api/pdv/webhooks/notagil');
        $payload['notagil_base_url'] = data_get($payload, 'notagil_base_url')
            ?: config('pdv.notagil.base_url', 'https://api.notagil.com.br/api/v1/integrations');
        $payload['notagil_webhook_tolerance_seconds'] = data_get($payload, 'notagil_webhook_tolerance_seconds')
            ?? (int) config('pdv.notagil.webhook_tolerance_seconds', 300);
        $payload['notagil_token_configured'] = $dbToken !== '' || $envToken !== '';
        $payload['notagil_webhook_secret_configured'] = $dbSecret !== '' || $envSecret !== '';
        $payload['notagil_nfce_synchronous'] = (bool) data_get($payload, 'notagil_nfce_synchronous', false);
        $payload['layout_cupom'] = $this->normalizeCupomLayoutPayload(data_get($payload, 'layout_cupom'));

        return $payload;
    }

    private function normalizeCupomLayoutPayload(mixed $layout): ?array
    {
        if (is_array($layout)) {
            return $layout;
        }

        if (is_string($layout) && trim($layout) !== '') {
            $decoded = json_decode($layout, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function validateFiscalPayload(Request $request): array
    {
        return $request->validate([
            'ambiente' => ['required', 'string'],
            'serie_nfe' => ['nullable', 'string', 'max:255'],
            'serie_nfce' => ['nullable', 'string', 'max:255'],
            'proximo_numero_nfe' => ['nullable', 'string', 'max:255'],
            'proximo_numero_nfce' => ['nullable', 'string', 'max:255'],
            'csc' => ['nullable', 'string', 'max:255'],
            'id_csc' => ['nullable', 'string', 'max:255'],
            'emitir_nfce' => ['required', 'boolean'],
            'emitir_nfe' => ['required', 'boolean'],
            'impressao_automatica' => ['required', 'boolean'],
            'notagil_enabled' => ['nullable', 'boolean'],
            'notagil_base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'notagil_token' => ['nullable', 'string', 'max:2048'],
            'notagil_company_id' => [Rule::requiredIf($request->boolean('notagil_enabled')), 'nullable', 'string', 'max:80'],
            'notagil_operation_code_nfce' => ['nullable', 'string', 'max:80'],
            'notagil_nfce_synchronous' => ['nullable', 'boolean'],
            'notagil_operation_code_nfe' => ['nullable', 'string', 'max:80'],
            'notagil_webhook_url' => ['nullable', 'string', 'max:2048'],
            'notagil_webhook_secret' => ['nullable', 'string', 'max:255'],
            'notagil_webhook_tolerance_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'logo_url' => ['nullable', 'string', 'max:3000000'],
            'layout_cupom' => ['nullable', 'array'],
        ]);
    }

    private function defaultNotagilWebhookUrl(): string
    {
        return config('pdv.notagil.webhook_url') ?: url('/api/pdv/webhooks/notagil');
    }
}
