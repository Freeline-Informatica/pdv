<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\FiscalItemProfile;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\ProdutoAuditoria;
use Freeline\Pdv\Models\ProdutoClassificacaoMercadologica;
use Freeline\Pdv\Models\ProdutoCodigoBarras;
use Freeline\Pdv\Models\ProdutoEstoque;
use Freeline\Pdv\Models\ProdutoFamilia;
use Freeline\Pdv\Models\ProdutoPreco;
use Freeline\Pdv\Models\UnidadeMedida;
use Freeline\Pdv\Services\NotaAgilConfigurationException;
use Freeline\Pdv\Services\NotaAgilFiscalService;
use Freeline\Pdv\Support\Gtin;
use Freeline\Pdv\Support\QuantityNormalizer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CatalogProductsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Produto::query()
            ->with(['unidadeMedida:id,unidade,descricao,decimais', 'familia:id,nome', 'classificacaoMercadologica:id,descricao', 'estoque:id,produto_id,quantidade,quantidade_minima'])
            ->withCount(['codigosBarras as codigos_barras_count', 'precos as precos_count'])
            ->orderBy('descricao');

        if ($request->filled('search')) {
            $needle = mb_strtolower(trim((string) $request->input('search')));
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(descricao) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(cod_sku, '')) LIKE ?", ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(codigo_operacional, '')) LIKE ?", ["%{$needle}%"]);
            });
        }

        $rows = $query->paginate((int) $request->integer('per_page', 15));

        $rows->getCollection()->transform(function (Produto $produto): array {
            $activePrice = $produto->precos()
                ->where('ativo', true)
                ->orderByRaw('CASE WHEN valor > 0 THEN 0 ELSE 1 END')
                ->orderBy('tipo')
                ->first();

            return [
                'id' => $produto->id,
                'descricao' => $produto->descricao,
                'descricao_curta' => $produto->descricao_curta,
                'cod_sku' => $produto->cod_sku,
                'codigo_operacional' => $produto->codigo_operacional,
                'situacao' => $produto->situacao,
                'liberado' => $produto->liberado,
                'marca' => $produto->marca,
                'unidade_medida' => $produto->unidadeMedida ? [
                    'id' => $produto->unidadeMedida->id,
                    'unidade' => $produto->unidadeMedida->unidade,
                    'descricao' => $produto->unidadeMedida->descricao,
                ] : null,
                'familia' => $produto->familia ? [
                    'id' => $produto->familia->id,
                    'nome' => $produto->familia->nome,
                ] : null,
                'classificacao_mercadologica' => $produto->classificacaoMercadologica ? [
                    'id' => $produto->classificacaoMercadologica->id,
                    'descricao' => $produto->classificacaoMercadologica->descricao,
                ] : null,
                'estoque_atual' => $this->formatProdutoQuantity($produto, $produto->estoque?->quantidade),
                'estoque_minimo' => $this->formatProdutoQuantity($produto, $produto->estoque?->quantidade_minima),
                'preco_venda' => $activePrice?->valor,
                'codigos_barras_count' => $produto->codigos_barras_count,
                'precos_count' => $produto->precos_count,
                'updated_at' => $produto->updated_at,
            ];
        });

        return response()->json($rows);
    }

    public function supportData(): JsonResponse
    {
        return response()->json([
            'unidades_medida' => UnidadeMedida::query()->where('status', true)->orderBy('unidade')->get(['id', 'unidade', 'descricao', 'decimais']),
            'familias' => ProdutoFamilia::query()->where('ativo', true)->orderBy('nome')->get(['id', 'codigo', 'nome']),
            'classificacoes_mercadologicas' => ProdutoClassificacaoMercadologica::query()
                ->where('ativo', true)
                ->orderBy('nivel')
                ->orderBy('ordem')
                ->orderBy('descricao')
                ->get(['id', 'parent_id', 'codigo', 'descricao', 'nivel']),
            'fiscal_item_profiles' => FiscalItemProfile::query()->where('active', true)->orderBy('display_name')->get(['id', 'display_name', 'item_type', 'ncm', 'ncm_descricao', 'cest']),
            'tipos_preco' => [
                ['id' => 'venda', 'label' => 'Venda'],
                ['id' => 'atacado', 'label' => 'Atacado'],
                ['id' => 'promocional', 'label' => 'Promocional'],
            ],
            'situacoes' => [
                ['id' => 'ativo', 'label' => 'Ativo'],
                ['id' => 'inativo', 'label' => 'Inativo'],
                ['id' => 'bloqueado', 'label' => 'Bloqueado'],
            ],
            'produto_tipos' => [
                ['id' => 'mercadoria', 'label' => 'Mercadoria'],
                ['id' => 'servico', 'label' => 'Serviço'],
                ['id' => 'composto', 'label' => 'Composto'],
            ],
        ]);
    }

    public function searchNcms(Request $request, NotaAgilFiscalService $notaAgil): JsonResponse
    {
        $payload = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($payload['search'] ?? ''));
        $limit = (int) ($payload['limit'] ?? 50);
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $filters = array_filter([
            'q' => $search,
            'limit' => $limit,
        ], static fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = $notaAgil->searchNcms($filters, FiscalConfig::query()->first());
        } catch (NotaAgilConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'data' => []], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível consultar os NCMs no NotaAgil.',
                'data' => [],
            ], 502);
        }

        return response()->json([
            'data' => $this->normalizeNcmRows($response, $limit),
        ]);
    }

    public function show(Produto $produto): JsonResponse
    {
        $produto->load([
            'precos',
            'codigosBarras',
            'estoque',
            'auditorias.user:id,name',
        ]);

        return response()->json($this->serializeProduto($produto));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $produto = DB::transaction(function () use ($request, $payload): Produto {
            $produto = new Produto();
            $this->persistProduto($produto, $payload);

            $this->registerAudit($request, $produto, 'created', [
                'after' => $produto->fresh()?->only($produto->getFillable()),
                'children' => [
                    'precos' => count($payload['precos'] ?? []),
                    'codigos_barras' => count($payload['codigos_barras'] ?? []),
                    'estoque' => ! empty($payload['estoque']),
                ],
            ]);

            return $produto;
        });

        return response()->json($this->serializeProduto($produto->fresh()), 201);
    }

    public function update(Request $request, Produto $produto): JsonResponse
    {
        $payload = $this->validatePayload($request, $produto);

        DB::transaction(function () use ($request, $payload, $produto): void {
            $before = Arr::only($produto->toArray(), $produto->getFillable());
            $beforePrecos = $this->snapshotPrecos($produto);
            $beforeCodigosBarras = $this->snapshotCodigosBarras($produto);
            $beforeEstoque = $this->snapshotEstoque($produto);

            $this->persistProduto($produto, $payload);

            $afterModel = $produto->fresh(['precos', 'codigosBarras', 'estoque']);
            $after = $afterModel ? Arr::only($afterModel->toArray(), $produto->getFillable()) : [];

            $changes = [];
            foreach ($after as $field => $value) {
                $previous = $before[$field] ?? null;
                if ($this->valuesAreDifferent($previous, $value)) {
                    $changes[$field] = [
                        'before' => $previous,
                        'after' => $value,
                    ];
                }
            }

            $afterPrecos = $this->snapshotPrecos($afterModel);
            if ($this->valuesAreDifferent($beforePrecos, $afterPrecos)) {
                $changes['precos'] = [
                    'before' => $beforePrecos,
                    'after' => $afterPrecos,
                ];
            }

            $afterCodigosBarras = $this->snapshotCodigosBarras($afterModel);
            if ($this->valuesAreDifferent($beforeCodigosBarras, $afterCodigosBarras)) {
                $changes['codigos_barras'] = [
                    'before' => $beforeCodigosBarras,
                    'after' => $afterCodigosBarras,
                ];
            }

            $afterEstoque = $this->snapshotEstoque($afterModel);
            if ($this->valuesAreDifferent($beforeEstoque, $afterEstoque)) {
                $changes['estoque'] = [
                    'before' => $beforeEstoque,
                    'after' => $afterEstoque,
                ];
            }

            if (! empty($changes)) {
                $this->registerAudit($request, $produto, 'updated', [
                    'fields' => $changes,
                    'children' => [
                        'precos' => count($payload['precos'] ?? []),
                        'codigos_barras' => count($payload['codigos_barras'] ?? []),
                        'estoque' => ! empty($payload['estoque']),
                    ],
                ]);
            }
        });

        return response()->json($this->serializeProduto($produto->fresh()));
    }

    public function destroy(Request $request, Produto $produto): JsonResponse
    {
        DB::transaction(function () use ($request, $produto): void {
            $this->registerAudit($request, $produto, 'deleted', [
                'before' => Arr::only($produto->toArray(), $produto->getFillable()),
            ]);

            $produto->delete();
        });

        return response()->json(['message' => 'Produto removido com sucesso.']);
    }

    private function validatePayload(Request $request, ?Produto $produto = null): array
    {
        $this->sanitizeBarcodeInput($request);
        $this->normalizeQuantityInputs($request);

        $payload = $request->validate([
            'estabelecimento_id' => ['nullable', 'uuid'],
            'produto_mestre_id' => ['nullable', 'uuid'],
            'fiscal_item_profile_id' => ['nullable', 'uuid', 'exists:fiscal_item_profiles,id'],
            'fiscal_item_profile_entrada_id' => ['nullable', 'uuid', 'exists:fiscal_item_profiles,id'],
            'fiscal_item_profile_saida_id' => ['nullable', 'uuid', 'exists:fiscal_item_profiles,id'],
            'classificacao_mercadologica_id' => ['nullable', 'uuid', 'exists:produto_classificacao_mercadologica,id'],
            'unidade_medida_id' => ['nullable', 'uuid', 'exists:unidade_medida,id'],
            'produto_familia_id' => ['nullable', 'uuid', 'exists:produto_familia,id'],
            'cod_sku' => ['nullable', 'string', 'max:100'],
            'codigo_operacional' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('produto', 'codigo_operacional')->ignore($produto?->id)->where(fn ($query) => $query->where('estabelecimento_id', $request->input('estabelecimento_id'))),
            ],
            'codigo_operacional_manual' => ['sometimes', 'boolean'],
            'descricao' => ['required', 'string', 'max:255'],
            'descricao_curta' => ['nullable', 'string', 'max:255'],
            'produto_tipo' => ['nullable', 'string', 'max:50'],
            'situacao' => ['nullable', 'string', 'max:50'],
            'liberado' => ['required', Rule::in(['sim', 'nao'])],
            'marca' => ['nullable', 'string', 'max:50'],
            'palavra_chave' => ['nullable', 'string', 'max:100'],
            'permite_fracionamento' => ['sometimes', 'boolean'],
            'atributos_logisticos' => ['nullable', 'array'],
            'atributos_logisticos.fiscal_ncm' => ['nullable', 'string', 'max:20'],
            'atributos_logisticos.fiscal_ncm_ex' => ['nullable', 'string', 'max:20'],
            'atributos_logisticos.fiscal_cest' => ['nullable', 'string', 'max:20'],
            'atributos_logisticos.fiscal_origem' => ['nullable', 'string', 'max:30'],
            'atributos_logisticos.fiscal_tax_classification_code' => ['nullable', 'string', 'max:30'],
            'atributos_logisticos.conta_contabil' => ['nullable', 'string', 'max:100'],
            'atributos_logisticos.nr_contrato' => ['nullable', 'string', 'max:100'],
            'atributos_logisticos.classificacoes_niveis_adicionais' => ['nullable', 'array'],
            'atributos_logisticos.classificacoes_niveis_adicionais.*' => ['nullable', 'string', 'max:120'],
            'atributos_logisticos.descricao_site' => ['nullable', 'string'],
            'atributos_logisticos.descricao_detalhada' => ['nullable', 'string'],
            'atributos_logisticos.empresas_vinculadas' => ['nullable', 'array'],
            'atributos_logisticos.empresas_vinculadas.*' => ['nullable', 'string', 'max:120'],
            'atributos_logisticos.clientes_vinculados' => ['nullable', 'array'],
            'atributos_logisticos.clientes_vinculados.*' => ['nullable', 'string', 'max:120'],
            'atributos_logisticos.estoque_detalhado' => ['nullable', 'array'],
            'atributos_logisticos.gerencial_memoria' => ['nullable', 'array'],
            'atributos_logisticos.informacao_adicional' => ['nullable', 'array'],
            'atributos_logisticos.informacao_adicional.composicoes' => ['nullable', 'array'],
            'atributos_logisticos.informacao_adicional.fotos' => ['nullable', 'array'],
            'atributos_logisticos.informacao_adicional.fotos.*.nome' => ['nullable', 'string', 'max:255'],
            'atributos_logisticos.informacao_adicional.fotos.*.url' => ['nullable', 'string'],
            'atributos_logisticos.informacao_adicional.composicoes.*.id' => ['nullable', 'uuid'],
            'atributos_logisticos.informacao_adicional.composicoes.*.produto_id' => ['nullable', 'uuid'],
            'atributos_logisticos.informacao_adicional.composicoes.*.produto' => ['nullable', 'string', 'max:255'],
            'atributos_logisticos.informacao_adicional.composicoes.*.parent_id' => ['nullable', 'string', 'max:60'],
            'atributos_logisticos.informacao_adicional.composicoes.*.quantidade' => ['nullable', 'numeric', 'min:0'],
            'atributos_logisticos.informacao_adicional.composicoes.*.ordem' => ['nullable', 'string', 'max:50'],
            'atributos_logisticos.informacao_adicional.composicoes.*.calculate_cost' => ['nullable', 'boolean'],
            'atributos_logisticos.informacao_adicional.composicoes.*.operational_cost' => ['nullable', 'numeric', 'min:0'],
            'atributos_logisticos.informacao_adicional.composicoes.*.campos_adicionais' => ['nullable', 'array'],
            'atributos_logisticos.informacao_adicional.composicoes.*.campos_adicionais.*.operational_cost' => ['nullable', 'numeric', 'min:0'],

            'precos' => ['nullable', 'array'],
            'precos.*.id' => ['nullable', 'uuid'],
            'precos.*.tipo' => ['required_with:precos', 'string', 'max:50'],
            'precos.*.codigo' => ['nullable', 'string', 'max:60'],
            'precos.*.canal' => ['nullable', 'string', 'max:50'],
            'precos.*.valor' => ['required_with:precos', 'numeric', 'min:0'],
            'precos.*.percentual' => ['nullable', 'numeric'],
            'precos.*.custo_referencial' => ['nullable', 'numeric', 'min:0'],
            'precos.*.margem' => ['nullable', 'numeric'],
            'precos.*.margem_preco_minimo' => ['nullable', 'numeric'],
            'precos.*.vigencia_inicio' => ['nullable', 'date'],
            'precos.*.vigencia_fim' => ['nullable', 'date'],
            'precos.*.ativo' => ['sometimes', 'boolean'],

            'codigos_barras' => ['nullable', 'array'],
            'codigos_barras.*.id' => ['nullable', 'uuid'],
            'codigos_barras.*.produto_apresentacao_id' => ['nullable', 'uuid', 'exists:produto_apresentacao,id'],
            'codigos_barras.*.codigo' => [
                'required_with:codigos_barras',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $type = $this->barcodeRowFromAttribute($request, $attribute)['tipo_codigo'] ?? 'GTIN-13';
                    $message = Gtin::validationMessage($value, $type);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
            'codigos_barras.*.tipo_codigo' => [
                'nullable',
                'string',
                'max:20',
                Rule::in(Gtin::allowedTypes()),
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $row = $this->barcodeRowFromAttribute($request, $attribute);

                    if ($this->isBoxBarcodeRow($row) && Gtin::normalizeType((string) $value) === 'EAN-8') {
                        $fail('Código de barras da caixa aceita somente GTIN-14 ou GTIN-13.');
                    }
                },
            ],
            'codigos_barras.*.principal' => ['sometimes', 'boolean'],
            'codigos_barras.*.informacoes_complementares' => ['nullable', 'string', 'max:255'],
            'codigos_barras.*.ativo' => ['sometimes', 'boolean'],

            'estoque' => ['nullable', 'array'],
            'estoque.quantidade' => ['nullable', 'numeric'],
            'estoque.quantidade_minima' => ['nullable', 'numeric'],
            'estoque.quantidade_maxima' => ['nullable', 'numeric'],
            'estoque.numero_lote' => ['nullable', 'string', 'max:255'],
            'estoque.reduzir_estoque' => ['sometimes', 'boolean'],
            'estoque.quantidade_minima_vendavel' => ['nullable', 'numeric'],
            'estoque.quantidade_alerta' => ['nullable', 'numeric'],
        ]);

        return $this->normalizeFiscalPayload($payload);
    }

    private function normalizeQuantityInputs(Request $request): void
    {
        $unit = UnidadeMedida::query()->find($request->input('unidade_medida_id'));
        $allowsFractional = $this->unitAllowsFractionalQuantity($unit) || $request->boolean('permite_fracionamento');
        $payload = $request->all();
        $errors = [];
        $manualReorderPoint = $this->hasManualReorderPointOverride($payload);

        foreach ($this->quantityPayloadPaths() as $path) {
            if (! Arr::has($payload, $path)) {
                continue;
            }

            if ($path === 'atributos_logisticos.estoque_detalhado.ponto_pedido' && ! $manualReorderPoint) {
                continue;
            }

            $normalized = QuantityNormalizer::normalize(Arr::get($payload, $path), $allowsFractional);
            if (! $normalized['valid']) {
                $errors[$path] = [$normalized['message']];
                continue;
            }

            Arr::set($payload, $path, $normalized['value']);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->syncCalculatedReorderPoint($payload, $allowsFractional);

        $request->replace($payload);
    }

    private function quantityPayloadPaths(): array
    {
        return [
            'estoque.quantidade',
            'estoque.quantidade_minima',
            'estoque.quantidade_maxima',
            'estoque.quantidade_minima_vendavel',
            'estoque.quantidade_alerta',
            'atributos_logisticos.estoque_detalhado.consumo_médio_diario',
            'atributos_logisticos.estoque_detalhado.estoque_segurança',
            'atributos_logisticos.estoque_detalhado.lote_minimo_compra',
            'atributos_logisticos.estoque_detalhado.ponto_pedido',
        ];
    }

    private function unitAllowsFractionalQuantity(?UnidadeMedida $unit): bool
    {
        if (! $unit) {
            return false;
        }

        if ((int) ($unit->decimais ?? 0) > 0) {
            return true;
        }

        return in_array(mb_strtoupper((string) $unit->unidade), ['KG', 'G', 'GR', 'L', 'LT', 'ML', 'M', 'MT', 'M2', 'M3'], true);
    }

    private function hasManualReorderPointOverride(array $payload): bool
    {
        return filter_var(
            Arr::get($payload, 'atributos_logisticos.estoque_detalhado.ponto_pedido_override', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function syncCalculatedReorderPoint(array &$payload, bool $allowsFractional): void
    {
        if ($this->hasManualReorderPointOverride($payload)) {
            return;
        }

        $dailyConsumption = $this->quantityPayloadNumber(Arr::get($payload, 'atributos_logisticos.estoque_detalhado.consumo_médio_diario'));
        $leadDays = $this->quantityPayloadNumber(Arr::get($payload, 'atributos_logisticos.estoque_detalhado.lead_time_compra'))
            + $this->quantityPayloadNumber(Arr::get($payload, 'atributos_logisticos.estoque_detalhado.lead_time_entrega'))
            + $this->quantityPayloadNumber(Arr::get($payload, 'atributos_logisticos.estoque_detalhado.lead_time_recebimento'));
        $safetyStock = $this->quantityPayloadNumber(Arr::get($payload, 'atributos_logisticos.estoque_detalhado.estoque_segurança'));
        $calculated = max(0, ($dailyConsumption * max(0, $leadDays)) + $safetyStock);
        $calculated = $allowsFractional ? round($calculated, 3) : ceil($calculated);

        $normalized = QuantityNormalizer::normalize($calculated, $allowsFractional);
        Arr::set($payload, 'atributos_logisticos.estoque_detalhado.ponto_pedido', $normalized['value'] ?? '0');
    }

    private function quantityPayloadNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : 0.0;
        }

        $text = trim((string) $value);
        $normalized = str_contains($text, ',')
            ? str_replace(',', '.', str_replace('.', '', $text))
            : preg_replace('/[^\d.-]/', '', $text);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function formatProdutoQuantity(Produto $produto, mixed $value): ?string
    {
        return QuantityNormalizer::formatForDisplay(
            $value,
            (bool) $produto->permite_fracionamento || $this->unitAllowsFractionalQuantity($produto->unidadeMedida),
        );
    }

    private function sanitizeBarcodeInput(Request $request): void
    {
        if (! $request->has('codigos_barras') || ! is_array($request->input('codigos_barras'))) {
            return;
        }

        $request->merge([
            'codigos_barras' => collect($request->input('codigos_barras'))
                ->map(function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }

                    $row['codigo'] = Gtin::sanitize($row['codigo'] ?? '');

                    if (array_key_exists('tipo_codigo', $row)) {
                        $row['tipo_codigo'] = Gtin::normalizeType((string) $row['tipo_codigo']);
                    }

                    return $row;
                })
                ->all(),
        ]);
    }

    private function barcodeRowFromAttribute(Request $request, string $attribute): array
    {
        if (! preg_match('/^codigos_barras\.(\d+)\./', $attribute, $matches)) {
            return [];
        }

        $row = $request->input("codigos_barras.{$matches[1]}", []);

        return is_array($row) ? $row : [];
    }

    private function isBoxBarcodeRow(array $row): bool
    {
        return mb_stripos((string) ($row['informacoes_complementares'] ?? ''), 'caixa') !== false;
    }

    private function persistProduto(Produto $produto, array $payload): void
    {
        $produto->fill(Arr::only($payload, $produto->getFillable()));
        $produto->save();

        $this->syncPrecos($produto, $payload['precos'] ?? []);
        $this->syncCodigosBarras($produto, $payload['codigos_barras'] ?? []);
        $this->syncEstoque($produto, $payload['estoque'] ?? null);
    }

    private function normalizeFiscalPayload(array $payload): array
    {
        if (! array_key_exists('atributos_logisticos', $payload) || ! is_array($payload['atributos_logisticos'])) {
            return $payload;
        }

        $payload['atributos_logisticos']['fiscal_ncm'] = $this->digitsOrNull(
            $payload['atributos_logisticos']['fiscal_ncm'] ?? null,
        );

        return $payload;
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function normalizeNcmRows(array $response, int $limit): array
    {
        $rows = $response;

        foreach (['data', 'items', 'results', 'records'] as $key) {
            $candidate = data_get($response, $key);
            if (is_array($candidate)) {
                $rows = $candidate;
                break;
            }
        }

        if (Arr::isAssoc($rows)) {
            foreach (['items', 'results', 'records', 'data'] as $key) {
                $candidate = data_get($rows, $key);
                if (is_array($candidate)) {
                    $rows = $candidate;
                    break;
                }
            }
        }

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $code = (string) (
                    data_get($row, 'ncm')
                    ?: data_get($row, 'code')
                    ?: data_get($row, 'codigo')
                    ?: data_get($row, 'value')
                    ?: ''
                );
                $description = (string) (
                    data_get($row, 'description')
                    ?: data_get($row, 'descricao')
                    ?: data_get($row, 'ncm_descricao')
                    ?: data_get($row, 'name')
                    ?: data_get($row, 'label')
                    ?: ''
                );

                $normalizedCode = $this->digitsOrNull($code);

                return [
                    'id' => (string) (data_get($row, 'id') ?: $normalizedCode ?: trim($code)),
                    'ncm' => $normalizedCode ?: trim($code),
                    'ncm_descricao' => trim($description),
                    'display_name' => trim($description) ?: trim($code),
                    'cest' => (string) (data_get($row, 'cest') ?: ''),
                ];
            })
            ->filter(fn (array $row): bool => $row['ncm'] !== '')
            ->take($limit)
            ->values()
            ->all();
    }

    private function syncPrecos(Produto $produto, array $precos): void
    {
        $keptIds = [];

        foreach ($precos as $row) {
            $record = null;
            $rowId = (string) ($row['id'] ?? '');
            if ($rowId !== '') {
                $record = ProdutoPreco::query()->where('produto_id', $produto->id)->where('id', $rowId)->first();
            }

            if (! $record) {
                $record = new ProdutoPreco();
                $record->produto_id = $produto->id;
            }

            $record->fill([
                'tipo' => $row['tipo'] ?? 'venda',
                'codigo' => $row['codigo'] ?? null,
                'canal' => $row['canal'] ?? null,
                'valor' => $row['valor'] ?? 0,
                'percentual' => $row['percentual'] ?? null,
                'custo_referencial' => $row['custo_referencial'] ?? null,
                'margem' => $row['margem'] ?? null,
                'margem_preco_minimo' => $row['margem_preco_minimo'] ?? null,
                'vigencia_inicio' => $row['vigencia_inicio'] ?? null,
                'vigencia_fim' => $row['vigencia_fim'] ?? null,
                'ativo' => (bool) ($row['ativo'] ?? true),
            ]);
            $record->save();

            $keptIds[] = $record->id;
        }

        ProdutoPreco::query()
            ->where('produto_id', $produto->id)
            ->when(count($keptIds), fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when(! count($keptIds), fn ($query) => $query)
            ->delete();
    }

    private function syncCodigosBarras(Produto $produto, array $codigos): void
    {
        $keptIds = [];

        foreach ($codigos as $row) {
            $record = null;
            $rowId = (string) ($row['id'] ?? '');
            if ($rowId !== '') {
                $record = ProdutoCodigoBarras::query()->where('produto_id', $produto->id)->where('id', $rowId)->first();
            }

            if (! $record) {
                $record = new ProdutoCodigoBarras();
                $record->produto_id = $produto->id;
            }

            $record->fill([
                'produto_apresentacao_id' => $row['produto_apresentacao_id'] ?? null,
                'codigo' => $row['codigo'] ?? '',
                'tipo_codigo' => $row['tipo_codigo'] ?? null,
                'principal' => (bool) ($row['principal'] ?? false),
                'informacoes_complementares' => $row['informacoes_complementares'] ?? null,
                'ativo' => (bool) ($row['ativo'] ?? true),
            ]);
            $record->save();

            $keptIds[] = $record->id;
        }

        ProdutoCodigoBarras::query()
            ->where('produto_id', $produto->id)
            ->when(count($keptIds), fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when(! count($keptIds), fn ($query) => $query)
            ->delete();
    }

    private function syncEstoque(Produto $produto, ?array $estoque): void
    {
        if (! is_array($estoque)) {
            return;
        }

        $record = ProdutoEstoque::query()->firstOrNew(['produto_id' => $produto->id]);

        $record->fill([
            'quantidade' => $estoque['quantidade'] ?? 0,
            'quantidade_minima' => $estoque['quantidade_minima'] ?? null,
            'quantidade_maxima' => $estoque['quantidade_maxima'] ?? null,
            'numero_lote' => $estoque['numero_lote'] ?? null,
            'reduzir_estoque' => (bool) ($estoque['reduzir_estoque'] ?? true),
            'quantidade_minima_vendavel' => $estoque['quantidade_minima_vendavel'] ?? null,
            'quantidade_alerta' => $estoque['quantidade_alerta'] ?? null,
        ]);

        $record->save();
    }

    private function registerAudit(Request $request, Produto $produto, string $event, array $changes): void
    {
        ProdutoAuditoria::query()->create([
            'produto_id' => $produto->id,
            'user_id' => $request->user()?->id,
            'entidade_tipo' => 'produto',
            'entidade_id' => $produto->id,
            'evento' => $event,
            'alteracoes' => $changes,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function serializeProduto(?Produto $produto): ?array
    {
        if (! $produto) {
            return null;
        }

        $produto->loadMissing(['unidadeMedida:id,unidade,decimais', 'precos', 'codigosBarras', 'estoque', 'auditorias.user:id,name']);

        return [
            'id' => $produto->id,
            'estabelecimento_id' => $produto->estabelecimento_id,
            'produto_mestre_id' => $produto->produto_mestre_id,
            'fiscal_item_profile_id' => $produto->fiscal_item_profile_id,
            'fiscal_item_profile_entrada_id' => $produto->fiscal_item_profile_entrada_id,
            'fiscal_item_profile_saida_id' => $produto->fiscal_item_profile_saida_id,
            'classificacao_mercadologica_id' => $produto->classificacao_mercadologica_id,
            'unidade_medida_id' => $produto->unidade_medida_id,
            'produto_familia_id' => $produto->produto_familia_id,
            'cod_sku' => $produto->cod_sku,
            'codigo_operacional' => $produto->codigo_operacional,
            'codigo_operacional_manual' => (bool) $produto->codigo_operacional_manual,
            'descricao' => $produto->descricao,
            'descricao_curta' => $produto->descricao_curta,
            'produto_tipo' => $produto->produto_tipo,
            'situacao' => $produto->situacao,
            'liberado' => $produto->liberado,
            'marca' => $produto->marca,
            'palavra_chave' => $produto->palavra_chave,
            'permite_fracionamento' => (bool) $produto->permite_fracionamento,
            'atributos_logisticos' => $produto->atributos_logisticos,
            'precos' => $produto->precos->map(fn (ProdutoPreco $preco) => [
                'id' => $preco->id,
                'tipo' => $preco->tipo,
                'codigo' => $preco->codigo,
                'canal' => $preco->canal,
                'valor' => $preco->valor,
                'percentual' => $preco->percentual,
                'custo_referencial' => $preco->custo_referencial,
                'margem' => $preco->margem,
                'margem_preco_minimo' => $preco->margem_preco_minimo,
                'vigencia_inicio' => optional($preco->vigencia_inicio)->format('Y-m-d'),
                'vigencia_fim' => optional($preco->vigencia_fim)->format('Y-m-d'),
                'ativo' => (bool) $preco->ativo,
            ])->values()->all(),
            'codigos_barras' => $produto->codigosBarras->map(fn (ProdutoCodigoBarras $codigo) => [
                'id' => $codigo->id,
                'produto_apresentacao_id' => $codigo->produto_apresentacao_id,
                'codigo' => $codigo->codigo,
                'tipo_codigo' => $codigo->tipo_codigo,
                'principal' => (bool) $codigo->principal,
                'informacoes_complementares' => $codigo->informacoes_complementares,
                'ativo' => (bool) $codigo->ativo,
            ])->values()->all(),
            'estoque' => $produto->estoque ? [
                'id' => $produto->estoque->id,
                'quantidade' => $this->formatProdutoQuantity($produto, $produto->estoque->quantidade),
                'quantidade_minima' => $this->formatProdutoQuantity($produto, $produto->estoque->quantidade_minima),
                'quantidade_maxima' => $this->formatProdutoQuantity($produto, $produto->estoque->quantidade_maxima),
                'numero_lote' => $produto->estoque->numero_lote,
                'reduzir_estoque' => (bool) $produto->estoque->reduzir_estoque,
                'quantidade_minima_vendavel' => $this->formatProdutoQuantity($produto, $produto->estoque->quantidade_minima_vendavel),
                'quantidade_alerta' => $this->formatProdutoQuantity($produto, $produto->estoque->quantidade_alerta),
            ] : null,
            'auditoria' => $produto->auditorias->map(fn (ProdutoAuditoria $audit) => [
                'id' => $audit->id,
                'evento' => $audit->evento,
                'alteracoes' => $audit->alteracoes,
                'created_at' => $audit->created_at?->toIso8601String(),
                'usuario' => $audit->user?->name,
            ])->values()->all(),
            'created_at' => $produto->created_at?->toIso8601String(),
            'updated_at' => $produto->updated_at?->toIso8601String(),
        ];
    }

    private function valuesAreDifferent(mixed $before, mixed $after): bool
    {
        return $this->normalizeAuditComparable($before) !== $this->normalizeAuditComparable($after);
    }

    private function normalizeAuditComparable(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($this->sortRecursive($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if (is_object($value)) {
            $arrayValue = (array) $value;
            return json_encode($this->sortRecursive($arrayValue), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private function sortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortRecursive($value);
            } elseif (is_object($value)) {
                $payload[$key] = $this->sortRecursive((array) $value);
            }
        }

        if (Arr::isAssoc($payload)) {
            ksort($payload);
        }

        return $payload;
    }

    private function snapshotPrecos(?Produto $produto): array
    {
        if (! $produto) {
            return [];
        }

        $rows = $produto->relationLoaded('precos')
            ? $produto->precos
            : $produto->precos()->get();

        return $rows
            ->map(fn (ProdutoPreco $row) => [
                'id' => $row->id,
                'tipo' => $row->tipo,
                'codigo' => $row->codigo,
                'canal' => $row->canal,
                'valor' => $row->valor,
                'percentual' => $row->percentual,
                'custo_referencial' => $row->custo_referencial,
                'margem' => $row->margem,
                'margem_preco_minimo' => $row->margem_preco_minimo,
                'vigencia_inicio' => optional($row->vigencia_inicio)->format('Y-m-d'),
                'vigencia_fim' => optional($row->vigencia_fim)->format('Y-m-d'),
                'ativo' => (bool) $row->ativo,
            ])
            ->values()
            ->all();
    }

    private function snapshotCodigosBarras(?Produto $produto): array
    {
        if (! $produto) {
            return [];
        }

        $rows = $produto->relationLoaded('codigosBarras')
            ? $produto->codigosBarras
            : $produto->codigosBarras()->get();

        return $rows
            ->map(fn (ProdutoCodigoBarras $row) => [
                'id' => $row->id,
                'produto_apresentacao_id' => $row->produto_apresentacao_id,
                'codigo' => $row->codigo,
                'tipo_codigo' => $row->tipo_codigo,
                'principal' => (bool) $row->principal,
                'informacoes_complementares' => $row->informacoes_complementares,
                'ativo' => (bool) $row->ativo,
            ])
            ->values()
            ->all();
    }

    private function snapshotEstoque(?Produto $produto): ?array
    {
        if (! $produto) {
            return null;
        }

        $row = $produto->relationLoaded('estoque')
            ? $produto->estoque
            : $produto->estoque()->first();

        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'quantidade' => $row->quantidade,
            'quantidade_minima' => $row->quantidade_minima,
            'quantidade_maxima' => $row->quantidade_maxima,
            'numero_lote' => $row->numero_lote,
            'reduzir_estoque' => (bool) $row->reduzir_estoque,
            'quantidade_minima_vendavel' => $row->quantidade_minima_vendavel,
            'quantidade_alerta' => $row->quantidade_alerta,
        ];
    }
}
