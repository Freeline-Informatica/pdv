<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Acquirer;
use Freeline\Pdv\Models\AcquirerTefConfig;
use Freeline\Pdv\Models\AcquirerTerminal;
use Freeline\Pdv\Models\AcquirerTerminalRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcquirersController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Acquirer::query()->orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'ativo' => ['required', 'boolean'],
            'observacoes' => ['nullable', 'string'],
        ]);

        return response()->json(Acquirer::create($payload), 201);
    }

    public function update(Request $request, Acquirer $acquirer): JsonResponse
    {
        $payload = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'ativo' => ['required', 'boolean'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $acquirer->update($payload);

        return response()->json($acquirer);
    }

    public function destroy(Acquirer $acquirer): JsonResponse
    {
        $acquirer->delete();

        return response()->json(['message' => 'Adquirente removido.']);
    }

    public function terminals(Acquirer $acquirer): JsonResponse
    {
        $terminals = $acquirer->terminals()->orderBy('estacao')->get();

        return response()->json($terminals);
    }

    public function storeTerminal(Request $request, Acquirer $acquirer): JsonResponse
    {
        $payload = $request->validate([
            'tipo' => ['required', 'string', 'max:10'],
            'estacao' => ['required', 'integer', 'min:1'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'formula' => ['required', 'string', 'max:50'],
        ]);

        $terminal = $acquirer->terminals()->create($payload);

        return response()->json($terminal, 201);
    }

    public function updateTerminal(Request $request, AcquirerTerminal $terminal): JsonResponse
    {
        $payload = $request->validate([
            'tipo' => ['required', 'string', 'max:10'],
            'estacao' => ['required', 'integer', 'min:1'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'formula' => ['required', 'string', 'max:50'],
        ]);

        $terminal->update($payload);

        return response()->json($terminal);
    }

    public function destroyTerminal(AcquirerTerminal $terminal): JsonResponse
    {
        $terminal->delete();

        return response()->json(['message' => 'Terminal removido.']);
    }

    public function rates(Request $request, AcquirerTerminal $terminal): JsonResponse
    {
        $query = $terminal->rates()->orderBy('created_at');

        if ($request->filled('tipo_credito')) {
            $query->where('tipo_credito', $request->string('tipo_credito'));
        }

        return response()->json($query->get());
    }

    public function storeRate(Request $request, AcquirerTerminal $terminal): JsonResponse
    {
        $payload = $request->validate([
            'tipo_credito' => ['required', 'string', 'max:100'],
            'taxa_operadora' => ['nullable', 'numeric'],
            'recebe_em' => ['nullable', 'integer', 'min:1'],
            'parc_inicial' => ['required', 'integer', 'min:1'],
            'parc_final' => ['required', 'integer', 'min:1'],
            'parc_sugerida' => ['nullable', 'integer', 'min:1'],
            'parc_maximo' => ['nullable', 'integer', 'min:1'],
            'ativo' => ['required', 'boolean'],
        ]);

        $rate = $terminal->rates()->create($payload);

        return response()->json($rate, 201);
    }

    public function updateRate(Request $request, AcquirerTerminalRate $rate): JsonResponse
    {
        $payload = $request->validate([
            'tipo_credito' => ['required', 'string', 'max:100'],
            'taxa_operadora' => ['nullable', 'numeric'],
            'recebe_em' => ['nullable', 'integer', 'min:1'],
            'parc_inicial' => ['required', 'integer', 'min:1'],
            'parc_final' => ['required', 'integer', 'min:1'],
            'parc_sugerida' => ['nullable', 'integer', 'min:1'],
            'parc_maximo' => ['nullable', 'integer', 'min:1'],
            'ativo' => ['required', 'boolean'],
        ]);

        $rate->update($payload);

        return response()->json($rate);
    }

    public function destroyRate(AcquirerTerminalRate $rate): JsonResponse
    {
        $rate->delete();

        return response()->json(['message' => 'Taxa removida.']);
    }

    public function tef(AcquirerTerminal $terminal): JsonResponse
    {
        $tef = AcquirerTefConfig::query()->where('terminal_ref_id', $terminal->id)->first();

        return response()->json($tef);
    }

    public function upsertTef(Request $request, AcquirerTerminal $terminal): JsonResponse
    {
        $payload = $request->validate([
            'tipo_integracao' => ['required', 'string', 'max:100'],
            'diretorio_gerenciador' => ['nullable', 'string', 'max:255'],
            'diretorio_envio' => ['nullable', 'string', 'max:255'],
            'diretorio_retorno' => ['nullable', 'string', 'max:255'],
            'enviar_rede' => ['required', 'boolean'],
            'enviar_cnc' => ['required', 'boolean'],
            'v700' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
            'provedor' => ['nullable', 'string', 'max:100'],
        ]);

        $tef = AcquirerTefConfig::query()->firstOrNew(['terminal_ref_id' => $terminal->id]);

        $tef->fill([
            ...$payload,
            'acquirer_id' => $terminal->acquirer_id,
            'provedor' => $payload['provedor'] ?? $payload['tipo_integracao'],
        ]);

        $tef->save();

        return response()->json($tef);
    }

    public function destroyTef(AcquirerTefConfig $tef): JsonResponse
    {
        $tef->delete();

        return response()->json(['message' => 'Integração TEF removida.']);
    }
}
