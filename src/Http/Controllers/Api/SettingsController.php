<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\FiscalConfigProvider;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\DigitalCertificate;
use Freeline\Pdv\Models\FiscalConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct(
        private readonly CompanyContextResolver $companyContext,
        private readonly FiscalConfigProvider $fiscalConfig,
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
            return response()->json($this->fiscalConfig->current());
        }

        return response()->json(FiscalConfig::query()->first());
    }

    public function upsertFiscal(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return $this->erpManagedResponse('fiscal');
        }

        $payload = $request->validate([
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
            'notagil_company_id' => ['nullable', 'string', 'max:80'],
            'notagil_operation_code_nfce' => ['nullable', 'string', 'max:80'],
            'notagil_operation_code_nfe' => ['nullable', 'string', 'max:80'],
        ]);

        $record = FiscalConfig::query()->first() ?? new FiscalConfig();
        $record->fill($payload)->save();

        return response()->json($record);
    }

    public function certificate(): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json([
                'certificate' => data_get($this->fiscalConfig->current(), 'certificate'),
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
}
