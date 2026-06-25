<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\PaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentPlansController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json([]);
        }

        $query = PaymentPlan::query()
            ->with('paymentMethod:id,nome,tipo')
            ->orderBy('ordem_pdv')
            ->orderBy('nome');

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->string('payment_method_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $record = PaymentPlan::create($payload);

        return response()->json($record->load('paymentMethod:id,nome,tipo'), 201);
    }

    public function update(Request $request, PaymentPlan $paymentPlan): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $paymentPlan->update($payload);

        return response()->json($paymentPlan->load('paymentMethod:id,nome,tipo'));
    }

    public function destroy(PaymentPlan $paymentPlan): JsonResponse
    {
        $paymentPlan->delete();

        return response()->json(['message' => 'Plano removido.']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'ativo' => ['required', 'boolean'],
            'ordem_pdv' => ['required', 'integer', 'min:0'],
            'quantidade_parcelas' => ['required', 'integer', 'min:1'],
            'intervalo_parcelas' => ['required', 'integer', 'min:1'],
            'valor_minimo_parcela' => ['nullable', 'numeric', 'min:0'],
            'possui_juros' => ['required', 'boolean'],
            'percentual_juros' => ['nullable', 'numeric', 'min:0'],
            'exibir_pdv' => ['required', 'boolean'],
        ]);
    }
}
