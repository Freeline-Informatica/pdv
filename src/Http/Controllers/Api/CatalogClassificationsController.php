<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\ProdutoClassificacaoMercadologica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogClassificationsController extends Controller
{
    private const OBSERVATION_FIELD_TYPES = [
        'texto_curto',
        'texto_longo',
        'numero_inteiro',
        'numero_decimal',
        'data',
        'sim_nao',
        'checkbox_texto',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = ProdutoClassificacaoMercadologica::query()
            ->with(['parent:id,descricao,codigo'])
            ->withCount(['products'])
            ->orderBy('nivel')
            ->orderBy('ordem')
            ->orderBy('descricao');

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        if ($request->boolean('only_active')) {
            $query->where('ativo', true);
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower(trim((string) $request->input('search')));
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(descricao) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(codigo, '')) LIKE ?", ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(path, '')) LIKE ?", ["%{$needle}%"]);
            });
        }

        return response()->json($query->get()->map(fn (ProdutoClassificacaoMercadologica $classification): array => $this->serialize($classification))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $record = DB::transaction(function () use ($payload): ProdutoClassificacaoMercadologica {
            $classification = new ProdutoClassificacaoMercadologica();
            $this->persistClassification($classification, $payload);
            return $classification->fresh(['parent:id,descricao,codigo']);
        });

        $record->loadCount(['products']);

        return response()->json($this->serialize($record), 201);
    }

    public function update(Request $request, ProdutoClassificacaoMercadologica $classification): JsonResponse
    {
        $payload = $this->validatePayload($request, $classification);

        $record = DB::transaction(function () use ($classification, $payload): ProdutoClassificacaoMercadologica {
            $this->persistClassification($classification, $payload);
            return $classification->fresh(['parent:id,descricao,codigo']);
        });

        $record->loadCount(['products']);

        return response()->json($this->serialize($record));
    }

    public function destroy(ProdutoClassificacaoMercadologica $classification): JsonResponse
    {
        $activeChildrenExists = ProdutoClassificacaoMercadologica::query()
            ->where('parent_id', $classification->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($activeChildrenExists) {
            throw ValidationException::withMessages([
                'classification' => ['Remova ou reclassifique os filhos antes de excluir esta classificação.'],
            ]);
        }

        $productsLinked = Produto::query()
            ->where('classificacao_mercadologica_id', $classification->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($productsLinked) {
            throw ValidationException::withMessages([
                'classification' => ['Existem produtos vinculados a esta classificação.'],
            ]);
        }

        $classification->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Classificação removida.',
        ]);
    }

    private function validatePayload(Request $request, ?ProdutoClassificacaoMercadologica $classification = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'uuid', Rule::exists('produto_classificacao_mercadologica', 'id')->whereNull('deleted_at')],
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('produto_classificacao_mercadologica', 'codigo')->ignore($classification?->id),
            ],
            'descricao' => ['required', 'string', 'max:120'],
            'descricao_reduzida' => ['nullable', 'string', 'max:40'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['required', 'boolean'],
            'parametros_observacoes' => ['nullable', 'array', 'max:40'],
            'parametros_observacoes.*.nome_template' => ['nullable', 'string', 'max:80'],
            'parametros_observacoes.*.nome_personalizado' => ['nullable', 'string', 'max:120'],
            'parametros_observacoes.*.tipo_campo' => ['nullable', 'string', Rule::in(self::OBSERVATION_FIELD_TYPES)],
            'parametros_observacoes.*.texto_checkbox' => ['nullable', 'string', 'max:120'],
            'parametros_observacoes.*.obrigatorio' => ['nullable', 'boolean'],
            'parametros_observacoes.*.ordem' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function persistClassification(ProdutoClassificacaoMercadologica $classification, array $payload): void
    {
        $parentId = $payload['parent_id'] ?? null;
        $parent = null;

        if ($parentId) {
            $parent = ProdutoClassificacaoMercadologica::query()->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Classificação pai não encontrada.'],
                ]);
            }
        }

        if ($classification->exists && $parentId && $parentId === $classification->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['A classificação não pode ser pai dela mesma.'],
            ]);
        }

        if ($classification->exists && $parentId && $this->isDescendantOf($parentId, $classification->id)) {
            throw ValidationException::withMessages([
                'parent_id' => ['A classificação pai não pode ser um descendente da própria classificação.'],
            ]);
        }

        $classification->parent_id = $parentId;
        $classification->codigo = trim((string) $payload['codigo']);
        $classification->descricao = trim((string) $payload['descricao']);
        $classification->descricao_reduzida = $this->nullableTrim($payload['descricao_reduzida'] ?? null);
        $classification->ordem = array_key_exists('ordem', $payload) ? $payload['ordem'] : null;
        $classification->ativo = (bool) $payload['ativo'];
        $classification->parametros_observacoes = $this->sanitizeObservationParameters($payload['parametros_observacoes'] ?? []);
        $classification->nivel = $parent ? ((int) $parent->nivel + 1) : 1;
        $classification->path = $parent ? trim((string) $parent->path, '/').'/'.$classification->codigo : $classification->codigo;

        if (mb_strlen((string) $classification->path) > 255) {
            throw ValidationException::withMessages([
                'codigo' => ['O caminho hierárquico ultrapassa o limite permitido.'],
            ]);
        }

        $classification->save();

        $this->refreshDescendantsHierarchy($classification);
    }

    private function refreshDescendantsHierarchy(ProdutoClassificacaoMercadologica $classification): void
    {
        $children = ProdutoClassificacaoMercadologica::query()
            ->where('parent_id', $classification->id)
            ->get();

        foreach ($children as $child) {
            $child->nivel = ((int) $classification->nivel) + 1;
            $child->path = trim((string) $classification->path, '/').'/'.$child->codigo;
            $child->save();
            $this->refreshDescendantsHierarchy($child);
        }
    }

    private function isDescendantOf(string $candidateId, string $ancestorId): bool
    {
        $currentId = $candidateId;
        $guard = 0;

        while ($currentId && $guard < 300) {
            if ($currentId === $ancestorId) {
                return true;
            }

            $current = ProdutoClassificacaoMercadologica::query()->find($currentId);
            $currentId = (string) ($current?->parent_id ?? '');
            $guard++;
        }

        return false;
    }

    private function nullableTrim(?string $value): ?string
    {
        $cleaned = trim((string) $value);
        return $cleaned !== '' ? $cleaned : null;
    }

    private function sanitizeObservationParameters(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $template = trim((string) ($row['nome_template'] ?? ''));
            $customName = trim((string) ($row['nome_personalizado'] ?? ''));
            $type = trim((string) ($row['tipo_campo'] ?? 'texto_curto'));
            $checkboxText = trim((string) ($row['texto_checkbox'] ?? ''));
            $required = (bool) ($row['obrigatorio'] ?? false);
            $order = isset($row['ordem']) && $row['ordem'] !== '' ? (int) $row['ordem'] : $index;

            if (! in_array($type, self::OBSERVATION_FIELD_TYPES, true)) {
                $type = 'texto_curto';
            }

            if ($template === '') {
                $template = 'personalizado';
            }

            if ($template !== 'personalizado') {
                $customName = '';
            }

            if ($type !== 'checkbox_texto') {
                $checkboxText = '';
            }

            if ($template === 'personalizado' && $customName === '') {
                continue;
            }

            $normalized[] = [
                'id' => trim((string) ($row['id'] ?? '')) ?: (string) str()->uuid(),
                'nome_template' => $template,
                'nome_personalizado' => $customName,
                'tipo_campo' => $type,
                'texto_checkbox' => $checkboxText,
                'obrigatorio' => $required,
                'ordem' => max(0, $order),
            ];
        }

        usort($normalized, function (array $a, array $b): int {
            $orderA = (int) ($a['ordem'] ?? 0);
            $orderB = (int) ($b['ordem'] ?? 0);
            if ($orderA !== $orderB) return $orderA <=> $orderB;
            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        return $normalized;
    }

    private function serialize(?ProdutoClassificacaoMercadologica $classification): array
    {
        if (! $classification) {
            return [];
        }

        return [
            'id' => $classification->id,
            'parent_id' => $classification->parent_id,
            'codigo' => $classification->codigo,
            'descricao' => $classification->descricao,
            'descricao_reduzida' => $classification->descricao_reduzida,
            'nivel' => $classification->nivel,
            'path' => $classification->path,
            'ordem' => $classification->ordem,
            'ativo' => (bool) $classification->ativo,
            'parametros_observacoes' => array_values(is_array($classification->parametros_observacoes) ? $classification->parametros_observacoes : []),
            'products_count' => (int) ($classification->products_count ?? 0),
            'parent' => $classification->parent ? [
                'id' => $classification->parent->id,
                'codigo' => $classification->parent->codigo,
                'descricao' => $classification->parent->descricao,
            ] : null,
            'created_at' => $classification->created_at,
            'updated_at' => $classification->updated_at,
            'deleted_at' => $classification->deleted_at,
        ];
    }
}
