<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\FiscalConfigProvider;
use Freeline\Pdv\Contracts\ProductCatalogRepository;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\PosTerminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    private const VALID_LAYOUT_MODES = ['varejo', 'restaurante', 'servicos'];
    private const VALID_RESTAURANT_MODES = ['auto_atendimento', 'totem', 'caixa', 'comanda_bar', 'comanda_cozinha', 'comanda_garcom'];

    public function __construct(
        private readonly ProductCatalogRepository $products,
        private readonly FiscalConfigProvider $fiscalConfigProvider,
        private readonly CompanyContextResolver $companyContextResolver,
    ) {
    }

    public function categories(): JsonResponse
    {
        return response()->json($this->products->categories()->values());
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json($this->products->search([
            'category_id' => $request->query('category_id'),
            'search' => $request->query('search'),
            'limit' => $request->query('limit', 500),
        ])->values());
    }

    public function companyProfile(Request $request): JsonResponse
    {
        $company = config('pdv.mode') === 'standalone'
            ? CompanySetting::query()->first()?->toArray()
            : $this->companyContextResolver->current();
        $fiscal = config('pdv.mode') === 'standalone'
            ? FiscalConfig::query()->first()?->toArray()
            : $this->fiscalConfigProvider->current();
        $companyLayoutMode = $this->normalizeLayoutMode(data_get($company, 'pdv_layout_mode') ?? data_get($fiscal, 'pdv_layout_mode')) ?? 'varejo';
        $terminalSettings = $this->resolveTerminalSettings($request->query('terminal_id'), $companyLayoutMode);

        $addressParts = array_filter([
            trim((string) data_get($company, 'logradouro', '')),
            trim((string) data_get($company, 'numero', '')),
            trim((string) data_get($company, 'bairro', '')),
        ]);

        return response()->json([
            'name' => trim((string) (data_get($company, 'nome_fantasia') ?: data_get($company, 'razao_social') ?: '')),
            'cnpj' => (string) data_get($company, 'cnpj', ''),
            'ie' => (string) data_get($company, 'inscricao_estadual', ''),
            'address' => implode(', ', $addressParts),
            'city' => (string) data_get($company, 'cidade', ''),
            'state' => (string) data_get($company, 'uf', ''),
            'phone' => (string) data_get($company, 'telefone', ''),
            'document_model' => data_get($fiscal, 'emitir_nfce', true) ? 'NFC-e' : 'NF-e',
            'document_series' => (string) (data_get($fiscal, 'serie_nfce') ?: data_get($fiscal, 'serie_nfe') ?: '1'),
            'pdv_layout_mode' => $companyLayoutMode,
            'pdv_restaurant_mode' => $terminalSettings['restaurant_mode'] ?? null,
            'estabelecimento_id' => $this->companyContextResolver->currentEstablishmentId(),
            'grupo_empresarial_id' => $this->companyContextResolver->currentGroupId(),
        ]);
    }

    private function resolveTerminalSettings(mixed $terminalId, string $companyLayoutMode): ?array
    {
        $id = trim((string) $terminalId);
        if ($id === '') return null;

        $terminal = $this->scopedTerminalQuery()
            ->select(['id', 'ativo', 'pdv_restaurant_mode'])
            ->find($id);

        if (! $terminal || ! $terminal->ativo) {
            return null;
        }

        return [
            'restaurant_mode' => $this->normalizeRestaurantMode($companyLayoutMode, $terminal->pdv_restaurant_mode),
        ];
    }

    private function normalizeLayoutMode(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, self::VALID_LAYOUT_MODES, true) ? $normalized : null;
    }

    private function normalizeRestaurantMode(string $layoutMode, ?string $value): ?string
    {
        if ($layoutMode !== 'restaurante') {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));
        return in_array($normalized, self::VALID_RESTAURANT_MODES, true) ? $normalized : 'comanda_garcom';
    }

    private function scopedTerminalQuery()
    {
        $query = PosTerminal::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $groupId = $this->companyContextResolver->currentGroupId();
        $establishmentId = $this->companyContextResolver->currentEstablishmentId();

        if (! $groupId || ! $establishmentId) {
            abort(409, 'Selecione uma filial no ERP para usar o PDV.');
        }

        return $query
            ->where('grupo_empresarial_id', (string) $groupId)
            ->where('estabelecimento_id', (string) $establishmentId);
    }
}
