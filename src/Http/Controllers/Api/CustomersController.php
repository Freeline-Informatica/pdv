<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->orderByRaw('LOWER(nome)')
            ->orderByDesc('created_at');

        if ($request->boolean('active_only')) {
            $query->where('ativo', true);
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $digits = preg_replace('/\D+/', '', $needle);

            $query->where(function ($builder) use ($needle, $digits): void {
                $builder
                    ->whereRaw('LOWER(nome) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(nome_fantasia, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) like ?', ["%{$needle}%"]);

                if ($digits !== '') {
                    $builder
                        ->orWhereRaw('COALESCE(cpf_cnpj, \'\') like ?', ["%{$digits}%"])
                        ->orWhereRaw('COALESCE(telefone, \'\') like ?', ["%{$digits}%"]);
                }
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $normalizedDocument = $this->normalizeDocument($payload['cpf_cnpj'] ?? null);
        $this->ensureDocumentUnique($normalizedDocument);

        $customer = Customer::query()->create($this->attributesFromPayload($payload, $normalizedDocument));

        return response()->json($customer, 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $normalizedDocument = $this->normalizeDocument($payload['cpf_cnpj'] ?? null);
        $this->ensureDocumentUnique($normalizedDocument, $customer->id);

        $customer->fill($this->attributesFromPayload($payload, $normalizedDocument));
        $customer->save();

        return response()->json($customer->fresh());
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['ok' => true]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tipo_pessoa' => ['nullable', 'string', 'in:fisica,juridica'],
            'cpf_cnpj' => ['nullable', 'string', 'max:30'],
            'nome' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'cep' => ['nullable', 'string', 'max:12'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:40'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'pais' => ['nullable', 'string', 'max:80'],
            'inscricao_estadual' => ['nullable', 'string', 'max:40'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }

    private function attributesFromPayload(array $payload, ?string $normalizedDocument): array
    {
        return [
            'tipo_pessoa' => in_array(($payload['tipo_pessoa'] ?? 'fisica'), ['fisica', 'juridica'], true)
                ? $payload['tipo_pessoa']
                : 'fisica',
            'cpf_cnpj' => $normalizedDocument,
            'nome' => trim((string) $payload['nome']),
            'nome_fantasia' => $this->nullableTrim($payload['nome_fantasia'] ?? null),
            'telefone' => $this->nullableDigits($payload['telefone'] ?? null),
            'email' => $this->nullableTrim($payload['email'] ?? null),
            'cep' => $this->nullableDigits($payload['cep'] ?? null),
            'logradouro' => $this->nullableTrim($payload['logradouro'] ?? null),
            'numero' => $this->nullableTrim($payload['numero'] ?? null),
            'bairro' => $this->nullableTrim($payload['bairro'] ?? null),
            'complemento' => $this->nullableTrim($payload['complemento'] ?? null),
            'cidade' => $this->nullableTrim($payload['cidade'] ?? null),
            'uf' => $this->nullableUpper($payload['uf'] ?? null),
            'pais' => $this->nullableTrim($payload['pais'] ?? null) ?: 'Brasil',
            'inscricao_estadual' => $this->nullableTrim($payload['inscricao_estadual'] ?? null),
            'observacoes' => $this->nullableTrim($payload['observacoes'] ?? null),
            'ativo' => array_key_exists('ativo', $payload) ? (bool) $payload['ativo'] : true,
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function nullableDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return $digits !== '' ? $digits : null;
    }

    private function nullableUpper(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') return null;

        if (! in_array(strlen($digits), [11, 14], true)) {
            throw ValidationException::withMessages([
                'cpf_cnpj' => ['Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.'],
            ]);
        }

        return $digits;
    }

    private function ensureDocumentUnique(?string $document, ?string $ignoreId = null): void
    {
        if (! $document) return;

        $query = Customer::query()->where('cpf_cnpj', $document);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'cpf_cnpj' => ['Já existe um cliente com este CPF/CNPJ.'],
            ]);
        }
    }
}
