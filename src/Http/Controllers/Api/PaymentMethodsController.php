<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\PaymentMethodRepository;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodsController extends Controller
{
    public function __construct(private readonly PaymentMethodRepository $paymentMethods)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json($this->paymentMethods->enabledForPdv()->values());
        }

        $query = PaymentMethod::query()->orderBy('ordem_pdv')->orderBy('nome');

        if ($request->boolean('active_only')) {
            $query->where('ativo', true);
        }

        if ($request->boolean('installments_only')) {
            $query->where('permite_parcelamento', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $record = PaymentMethod::create($payload);

        return response()->json($record, 201);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $paymentMethod->update($payload);

        return response()->json($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->delete();

        return response()->json(['message' => 'Meio de pagamento removido.']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
            'tef_habilitado' => ['required', 'boolean'],
            'tef_provedor' => ['nullable', 'string', 'max:255'],
            'tef_adquirente' => ['nullable', 'string', 'max:255'],
            'parcelas_max' => ['nullable', 'integer', 'min:1'],
            'parcela_minima' => ['nullable', 'numeric', 'min:0'],
            'taxa_debito' => ['nullable', 'numeric'],
            'taxa_credito_vista' => ['nullable', 'numeric'],
            'taxa_credito_parcelado' => ['nullable', 'numeric'],
            'dias_recebimento' => ['nullable', 'integer', 'min:0'],
            'observacoes' => ['nullable', 'string'],
            'ordem_pdv' => ['required', 'integer', 'min:0'],
            'permite_troco' => ['required', 'boolean'],
            'permite_parcelamento' => ['required', 'boolean'],
            'permite_multiplos_pagamentos' => ['required', 'boolean'],
            'parcelas_min' => ['nullable', 'integer', 'min:1'],
            'sem_juros_ate' => ['nullable', 'integer', 'min:0'],
            'paf_intermediator_cnpj' => ['nullable', 'string', 'max:20'],
            'paf_intermediator_identifier' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
