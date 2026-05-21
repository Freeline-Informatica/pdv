<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\UnidadeMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogUnitsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UnidadeMedida::query()->orderByRaw('LOWER(unidade)');

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        if ($request->boolean('only_active')) {
            $query->where('status', true);
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower(trim((string) $request->input('search')));
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(unidade) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(descricao, '')) LIKE ?", ["%{$needle}%"])
                    ->orWhereRaw("LOWER(COALESCE(codigo_fiscal, '')) LIKE ?", ["%{$needle}%"]);
            });
        }

        return response()->json($query->get()->map(fn (UnidadeMedida $unit): array => $this->serialize($unit))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $record = UnidadeMedida::query()->create($payload);

        return response()->json($this->serialize($record->fresh()), 201);
    }

    public function update(Request $request, UnidadeMedida $unit): JsonResponse
    {
        $payload = $this->validatePayload($request, $unit);

        $unit->update($payload);

        return response()->json($this->serialize($unit->fresh()));
    }

    public function destroy(UnidadeMedida $unit): JsonResponse
    {
        $unit->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Unidade de medida removida.',
        ]);
    }

    private function validatePayload(Request $request, ?UnidadeMedida $unit = null): array
    {
        $unitId = $unit?->id;
        $estabelecimentoId = $request->input('estabelecimento_id');

        return $request->validate([
            'estabelecimento_id' => ['nullable', 'uuid'],
            'unidade' => [
                'required',
                'string',
                'max:20',
                Rule::unique('unidade_medida', 'unidade')
                    ->ignore($unitId)
                    ->where(fn ($query) => $query->where('estabelecimento_id', $estabelecimentoId)),
            ],
            'descricao' => ['required', 'string', 'max:120'],
            'descricao_plural' => ['nullable', 'string', 'max:120'],
            'artigo' => ['nullable', 'string', 'max:20'],
            'codigo_fiscal' => ['nullable', 'string', 'max:30'],
            'decimais' => ['required', 'integer', 'min:0', 'max:6'],
            'status' => ['required', 'boolean'],
        ]);
    }

    private function serialize(?UnidadeMedida $unit): array
    {
        if (! $unit) {
            return [];
        }

        return [
            'id' => $unit->id,
            'estabelecimento_id' => $unit->estabelecimento_id,
            'unidade' => $unit->unidade,
            'descricao' => $unit->descricao,
            'descricao_plural' => $unit->descricao_plural,
            'artigo' => $unit->artigo,
            'codigo_fiscal' => $unit->codigo_fiscal,
            'decimais' => $unit->decimais,
            'status' => (bool) $unit->status,
            'created_at' => $unit->created_at,
            'updated_at' => $unit->updated_at,
            'deleted_at' => $unit->deleted_at,
        ];
    }
}
