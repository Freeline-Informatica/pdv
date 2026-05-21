<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\ProdutoFamilia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogFamiliesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProdutoFamilia::query()->orderByRaw('LOWER(nome)');

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
                    ->whereRaw('LOWER(nome) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(codigo, '')) LIKE ?", ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(codigo_prefixo, '')) LIKE ?", ["%{$needle}%"]);
            });
        }

        return response()->json($query->get()->map(fn (ProdutoFamilia $family): array => $this->serialize($family))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $record = ProdutoFamilia::query()->create($payload);

        return response()->json($this->serialize($record->fresh()), 201);
    }

    public function update(Request $request, ProdutoFamilia $family): JsonResponse
    {
        $payload = $this->validatePayload($request, $family);

        $family->update($payload);

        return response()->json($this->serialize($family->fresh()));
    }

    public function destroy(ProdutoFamilia $family): JsonResponse
    {
        $family->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Família removida.',
        ]);
    }

    private function validatePayload(Request $request, ?ProdutoFamilia $family = null): array
    {
        $familyId = $family?->id;
        $groupId = $request->input('grupo_empresarial_id');

        return $request->validate([
            'grupo_empresarial_id' => ['nullable', 'uuid'],
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('produto_familia', 'codigo')
                    ->ignore($familyId)
                    ->where(fn ($query) => $query->where('grupo_empresarial_id', $groupId)),
            ],
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'codigo_prefixo' => ['nullable', 'string', 'max:30'],
            'modo_geracao_codigo' => ['nullable', 'string', 'max:40'],
            'faixa_inicial' => ['nullable', 'integer', 'min:0'],
            'faixa_final' => ['nullable', 'integer', 'min:0'],
            'proximo_numero' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['required', 'boolean'],
        ]);
    }

    private function serialize(?ProdutoFamilia $family): array
    {
        if (! $family) {
            return [];
        }

        return [
            'id' => $family->id,
            'grupo_empresarial_id' => $family->grupo_empresarial_id,
            'codigo' => $family->codigo,
            'nome' => $family->nome,
            'descricao' => $family->descricao,
            'codigo_prefixo' => $family->codigo_prefixo,
            'modo_geracao_codigo' => $family->modo_geracao_codigo,
            'faixa_inicial' => $family->faixa_inicial,
            'faixa_final' => $family->faixa_final,
            'proximo_numero' => $family->proximo_numero,
            'ativo' => (bool) $family->ativo,
            'created_at' => $family->created_at,
            'updated_at' => $family->updated_at,
            'deleted_at' => $family->deleted_at,
        ];
    }
}
