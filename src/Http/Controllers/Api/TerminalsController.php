<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CashRegisterSession;
use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\PosTerminal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TerminalsController extends Controller
{
    private const VALID_LAYOUT_MODES = ['varejo', 'restaurante', 'servicos'];
    private const VALID_RESTAURANT_MODES = ['auto_atendimento', 'totem', 'caixa', 'comanda_bar', 'comanda_cozinha', 'comanda_garcom'];
    private const VALID_CONNECTION_MODES = ['direct', 'network'];
    private const DEFAULT_RESTAURANT_MODE = 'comanda_garcom';

    public function __construct(
        private readonly CompanyContextResolver $companyContext,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->autoProvisionDefaultTerminalIfNeeded();

        $companyLayoutMode = $this->resolveCompanyLayoutMode();

        $items = $this->scopedTerminalQuery()
            ->orderBy('nome')
            ->orderBy('identificador')
            ->get()
            ->map(fn (PosTerminal $terminal): array => $this->applyCompanyLayoutContext($terminal, $companyLayoutMode))
            ->values();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate($this->validationRules());

        $normalized = $this->normalizeIdentifier($payload['identificador']);
        $this->ensureIdentifierUnique($normalized);

        $companyLayoutMode = $this->resolveCompanyLayoutMode();
        $scope = $this->currentScope();
        $deviceAccess = $this->resolveDeviceAccessPayload($payload);

        $terminal = PosTerminal::query()->create([
            'grupo_empresarial_id' => $scope['grupo_id'],
            'estabelecimento_id' => $scope['estabelecimento_id'],
            'nome' => trim((string) $payload['nome']),
            'identificador' => $normalized,
            'ativo' => array_key_exists('ativo', $payload) ? (bool) $payload['ativo'] : true,
            'pdv_layout_mode' => $companyLayoutMode,
            'pdv_restaurant_mode' => $this->resolveRestaurantMode($companyLayoutMode, $payload['pdv_restaurant_mode'] ?? null),
            ...$deviceAccess,
        ]);

        return response()->json($this->applyCompanyLayoutContext($terminal, $companyLayoutMode), 201);
    }

    public function update(Request $request, PosTerminal $posTerminal): JsonResponse
    {
        $this->ensureTerminalBelongsToCurrentScope($posTerminal);

        $payload = $request->validate($this->validationRules());

        $normalized = $this->normalizeIdentifier($payload['identificador']);
        $this->ensureIdentifierUnique($normalized, $posTerminal->id);

        $companyLayoutMode = $this->resolveCompanyLayoutMode();
        $nextRestaurantMode = $payload['pdv_restaurant_mode'] ?? $posTerminal->pdv_restaurant_mode;
        $devicePayload = array_merge(
            $this->deviceAccessToArray($posTerminal),
            $payload,
        );
        $deviceAccess = $this->resolveDeviceAccessPayload($devicePayload);

        $posTerminal->nome = trim((string) $payload['nome']);
        $posTerminal->identificador = $normalized;
        if (array_key_exists('ativo', $payload)) {
            $posTerminal->ativo = (bool) $payload['ativo'];
        }
        $posTerminal->pdv_layout_mode = $companyLayoutMode;
        $posTerminal->pdv_restaurant_mode = $this->resolveRestaurantMode($companyLayoutMode, $nextRestaurantMode);
        $posTerminal->fill($deviceAccess);

        $posTerminal->save();

        return response()->json($this->applyCompanyLayoutContext($posTerminal->fresh(), $companyLayoutMode));
    }

    public function destroy(PosTerminal $posTerminal): JsonResponse
    {
        $this->ensureTerminalBelongsToCurrentScope($posTerminal);

        $hasOpenCash = $this->scopedCashRegisterQuery()
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->whereRaw('UPPER(terminal_codigo) = ?', [mb_strtoupper((string) $posTerminal->identificador)])
            ->exists();

        if ($hasOpenCash) {
            throw ValidationException::withMessages([
                'terminal' => ['Não é possível excluir um terminal com caixa aberto.'],
            ]);
        }

        $posTerminal->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function validationRules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:80'],
            'identificador' => ['required', 'string', 'max:30'],
            'ativo' => ['nullable', 'boolean'],
            'pdv_restaurant_mode' => ['nullable', Rule::in(self::VALID_RESTAURANT_MODES)],
            'printer_connection_mode' => ['nullable', Rule::in(self::VALID_CONNECTION_MODES)],
            'printer_bridge_base_url' => ['nullable', 'string', 'max:255', 'required_if:printer_connection_mode,network', 'url:http,https'],
            'printer_bridge_device_id' => ['nullable', 'string', 'max:80', 'required_if:printer_connection_mode,network'],
            'scale_connection_mode' => ['nullable', Rule::in(self::VALID_CONNECTION_MODES)],
            'scale_bridge_base_url' => ['nullable', 'string', 'max:255', 'required_if:scale_connection_mode,network', 'url:http,https'],
            'scale_bridge_device_id' => ['nullable', 'string', 'max:80', 'required_if:scale_connection_mode,network'],
        ];
    }

    private function deviceAccessToArray(PosTerminal $terminal): array
    {
        return [
            'printer_connection_mode' => $terminal->printer_connection_mode,
            'printer_bridge_base_url' => $terminal->printer_bridge_base_url,
            'printer_bridge_device_id' => $terminal->printer_bridge_device_id,
            'scale_connection_mode' => $terminal->scale_connection_mode,
            'scale_bridge_base_url' => $terminal->scale_bridge_base_url,
            'scale_bridge_device_id' => $terminal->scale_bridge_device_id,
        ];
    }

    private function resolveDeviceAccessPayload(array $payload): array
    {
        $printer = $this->normalizeDeviceAccess(
            mode: $payload['printer_connection_mode'] ?? null,
            bridgeBaseUrl: $payload['printer_bridge_base_url'] ?? null,
            bridgeDeviceId: $payload['printer_bridge_device_id'] ?? null,
            fieldPrefix: 'printer',
        );

        $scale = $this->normalizeDeviceAccess(
            mode: $payload['scale_connection_mode'] ?? null,
            bridgeBaseUrl: $payload['scale_bridge_base_url'] ?? null,
            bridgeDeviceId: $payload['scale_bridge_device_id'] ?? null,
            fieldPrefix: 'scale',
        );

        return [
            'printer_connection_mode' => $printer['mode'],
            'printer_bridge_base_url' => $printer['bridge_base_url'],
            'printer_bridge_device_id' => $printer['bridge_device_id'],
            'scale_connection_mode' => $scale['mode'],
            'scale_bridge_base_url' => $scale['bridge_base_url'],
            'scale_bridge_device_id' => $scale['bridge_device_id'],
        ];
    }

    private function normalizeDeviceAccess(?string $mode, mixed $bridgeBaseUrl, mixed $bridgeDeviceId, string $fieldPrefix): array
    {
        $resolvedMode = $this->normalizeConnectionMode($mode);

        if ($resolvedMode === 'direct') {
            return [
                'mode' => 'direct',
                'bridge_base_url' => null,
                'bridge_device_id' => null,
            ];
        }

        $deviceId = trim((string) $bridgeDeviceId);
        if ($deviceId === '') {
            throw ValidationException::withMessages([
                "{$fieldPrefix}_bridge_device_id" => ['Informe o identificador do dispositivo no bridge.'],
            ]);
        }

        return [
            'mode' => 'network',
            'bridge_base_url' => $this->normalizeBridgeBaseUrlForStorage($bridgeBaseUrl, "{$fieldPrefix}_bridge_base_url"),
            'bridge_device_id' => $deviceId,
        ];
    }

    private function normalizeConnectionMode(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, self::VALID_CONNECTION_MODES, true)
            ? $normalized
            : 'direct';
    }

    private function normalizeBridgeBaseUrlForStorage(mixed $value, string $field): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw ValidationException::withMessages([
                $field => [$this->bridgeBaseUrlRequiredMessage($field)],
            ]);
        }

        $parts = parse_url($raw);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw ValidationException::withMessages([
                $field => ['Informe uma URL base válida para o bridge (ex: http://192.168.0.50:8787).'],
            ]);
        }

        $scheme = mb_strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                $field => ['A URL do bridge deve usar HTTP ou HTTPS.'],
            ]);
        }

        if (! empty($parts['query']) || ! empty($parts['fragment'])) {
            throw ValidationException::withMessages([
                $field => ['A URL base do bridge não pode conter query string ou fragmento.'],
            ]);
        }

        $base = $scheme.'://'.(string) $parts['host'];
        if (array_key_exists('port', $parts) && $parts['port']) {
            $base .= ':'.(int) $parts['port'];
        }

        $path = trim((string) ($parts['path'] ?? ''));
        if ($path !== '') {
            $base .= '/'.ltrim($path, '/');
        }

        return rtrim($base, '/');
    }

    private function normalizeBridgeBaseUrl(mixed $value, string $field): string
    {
        return $this->normalizeBridgeBaseUrlForStorage($value, $field);
    }

    private function presentBridgeBaseUrl(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? rtrim($normalized, '/') : null;
    }

    private function presentBridgeDeviceId(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function bridgeBaseUrlRequiredMessage(string $field): string
    {
        return match ($field) {
            'printer_bridge_base_url' => 'Informe a URL base do bridge da impressora.',
            'scale_bridge_base_url' => 'Informe a URL base do bridge da balança.',
            default => 'Informe a URL base do bridge para este dispositivo.',
        };
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'identificador' => ['Informe um identificador válido.'],
            ]);
        }

        if (preg_match('/\s/', $normalized)) {
            throw ValidationException::withMessages([
                'identificador' => ['O identificador não pode conter espaços.'],
            ]);
        }

        return $normalized;
    }

    private function ensureIdentifierUnique(string $identifier, ?string $ignoreId = null): void
    {
        $query = $this->scopedTerminalQuery()
            ->whereRaw('UPPER(identificador) = ?', [$identifier]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'identificador' => ['Já existe um terminal com este identificador.'],
            ]);
        }
    }

    private function resolveLayoutMode(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, self::VALID_LAYOUT_MODES, true) ? $normalized : 'varejo';
    }

    private function resolveCompanyLayoutMode(): string
    {
        if (config('pdv.mode') === 'erp') {
            return $this->resolveLayoutMode(data_get($this->companyContext->current(), 'pdv_layout_mode'));
        }

        $company = CompanySetting::query()->first();

        return $this->resolveLayoutMode($company?->pdv_layout_mode);
    }

    private function applyCompanyLayoutContext(PosTerminal $terminal, string $companyLayoutMode): array
    {
        $attributes = $terminal->toArray();
        $attributes['pdv_layout_mode'] = $companyLayoutMode;
        $attributes['pdv_restaurant_mode'] = $this->resolveRestaurantMode($companyLayoutMode, $terminal->pdv_restaurant_mode);

        $attributes['printer_connection_mode'] = $this->normalizeConnectionMode($terminal->printer_connection_mode);
        $attributes['scale_connection_mode'] = $this->normalizeConnectionMode($terminal->scale_connection_mode);

        if ($attributes['printer_connection_mode'] === 'direct') {
            $attributes['printer_bridge_base_url'] = null;
            $attributes['printer_bridge_device_id'] = null;
        } else {
            $attributes['printer_bridge_base_url'] = $this->presentBridgeBaseUrl($terminal->printer_bridge_base_url);
            $attributes['printer_bridge_device_id'] = $this->presentBridgeDeviceId($terminal->printer_bridge_device_id);
        }

        if ($attributes['scale_connection_mode'] === 'direct') {
            $attributes['scale_bridge_base_url'] = null;
            $attributes['scale_bridge_device_id'] = null;
        } else {
            $attributes['scale_bridge_base_url'] = $this->presentBridgeBaseUrl($terminal->scale_bridge_base_url);
            $attributes['scale_bridge_device_id'] = $this->presentBridgeDeviceId($terminal->scale_bridge_device_id);
        }

        return $attributes;
    }

    private function resolveRestaurantMode(string $layoutMode, ?string $value): ?string
    {
        if ($layoutMode !== 'restaurante') {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, self::VALID_RESTAURANT_MODES, true)
            ? $normalized
            : self::DEFAULT_RESTAURANT_MODE;
    }

    private function autoProvisionDefaultTerminalIfNeeded(): void
    {
        if (config('pdv.mode') !== 'erp') {
            return;
        }

        $scope = $this->currentScope();
        if (! $scope['estabelecimento_id'] || $this->scopedTerminalQuery()->exists()) {
            return;
        }

        $companyLayoutMode = $this->resolveCompanyLayoutMode();
        $identifier = $this->nextDefaultIdentifier();

        PosTerminal::query()->create([
            'grupo_empresarial_id' => $scope['grupo_id'],
            'estabelecimento_id' => $scope['estabelecimento_id'],
            'nome' => 'Caixa 01',
            'identificador' => $identifier,
            'ativo' => true,
            'pdv_layout_mode' => $companyLayoutMode,
            'pdv_restaurant_mode' => $this->resolveRestaurantMode($companyLayoutMode, null),
            'printer_connection_mode' => 'direct',
            'printer_bridge_base_url' => null,
            'printer_bridge_device_id' => null,
            'scale_connection_mode' => 'direct',
            'scale_bridge_base_url' => null,
            'scale_bridge_device_id' => null,
        ]);
    }

    private function nextDefaultIdentifier(): string
    {
        for ($number = 1; $number <= 99; $number++) {
            $identifier = 'CX'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $exists = $this->scopedTerminalQuery()
                ->whereRaw('UPPER(identificador) = ?', [$identifier])
                ->exists();

            if (! $exists) {
                return $identifier;
            }
        }

        return 'CX'.now()->format('His');
    }

    private function ensureTerminalBelongsToCurrentScope(PosTerminal $terminal): void
    {
        if (config('pdv.mode') !== 'erp') {
            return;
        }

        $scope = $this->currentScope();
        if ((string) $terminal->estabelecimento_id === (string) $scope['estabelecimento_id']) {
            return;
        }

        abort(404);
    }

    private function scopedTerminalQuery(): Builder
    {
        $query = PosTerminal::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $scope = $this->currentScope();

        return $query
            ->where('grupo_empresarial_id', $scope['grupo_id'])
            ->where('estabelecimento_id', $scope['estabelecimento_id']);
    }

    private function scopedCashRegisterQuery(): Builder
    {
        $query = CashRegisterSession::query();

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
