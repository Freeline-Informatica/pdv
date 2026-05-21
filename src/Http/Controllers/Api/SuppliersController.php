<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SuppliersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->orderByRaw('LOWER(nome)')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(nome) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(documento, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) like ?', ["%{$needle}%"]);
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $normalizedDocument = $this->normalizeDocument($payload['documento'] ?? null);
        $this->ensureDocumentUnique($normalizedDocument);

        $supplier = Supplier::query()->create([
            'nome' => trim((string) $payload['nome']),
            'documento' => $normalizedDocument,
            'telefone' => $this->nullableTrim($payload['telefone'] ?? null),
            'email' => $this->nullableTrim($payload['email'] ?? null),
            'endereco' => $this->nullableTrim($payload['endereco'] ?? null),
            'observacoes' => $this->nullableTrim($payload['observacoes'] ?? null),
            'ativo' => array_key_exists('ativo', $payload) ? (bool) $payload['ativo'] : true,
        ]);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $normalizedDocument = $this->normalizeDocument($payload['documento'] ?? null);
        $this->ensureDocumentUnique($normalizedDocument, $supplier->id);

        $supplier->nome = trim((string) $payload['nome']);
        $supplier->documento = $normalizedDocument;
        $supplier->telefone = $this->nullableTrim($payload['telefone'] ?? null);
        $supplier->email = $this->nullableTrim($payload['email'] ?? null);
        $supplier->endereco = $this->nullableTrim($payload['endereco'] ?? null);
        $supplier->observacoes = $this->nullableTrim($payload['observacoes'] ?? null);

        if (array_key_exists('ativo', $payload)) {
            $supplier->ativo = (bool) $payload['ativo'];
        }

        $supplier->save();

        return response()->json($supplier->fresh());
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:30'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }

    private function nullableTrim(?string $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') return null;

        if (! in_array(strlen($digits), [11, 14], true)) {
            throw ValidationException::withMessages([
                'documento' => ['Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.'],
            ]);
        }

        return $digits;
    }

    private function ensureDocumentUnique(?string $document, ?string $ignoreId = null): void
    {
        if (! $document) return;

        $query = Supplier::query()->where('documento', $document);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'documento' => ['Já existe um fornecedor com este CNPJ/CPF.'],
            ]);
        }
    }
}

