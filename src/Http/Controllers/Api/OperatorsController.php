<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperatorsController extends Controller
{
    public function index(): JsonResponse
    {
        $items = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('email')
            ->get()
            ->map(fn (User $user) => $this->present($user))
            ->values();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'codigo' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email_local' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'pin' => ['required', 'string', 'digits:6'],
            'nome' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'perfil' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
            'ativo' => ['required', 'boolean'],
        ]);

        $email = $this->codeToEmail($payload['email_local'] ?? $payload['codigo']);
        $emailExists = User::query()->where('email', $email)->exists();
        if ($emailExists) {
            return response()->json(['message' => 'Já existe um operador com esse e-mail de acesso.'], 422);
        }

        $user = new User();
        $user->name = trim($payload['nome']);
        $user->email = $email;
        $user->role = $payload['perfil'];
        $user->password = Hash::make($payload['password'] ?? Str::random(32));
        $user->pin_hash = Hash::make($payload['pin']);
        $user->email_verified_at = $payload['ativo'] ? now() : null;
        $user->save();

        return response()->json($this->present($user), 201);
    }

    public function update(Request $request, User $operator): JsonResponse
    {
        $payload = $request->validate([
            'codigo' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email_local' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'pin' => ['nullable', 'string', 'digits:6'],
            'nome' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'perfil' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
            'ativo' => ['required', 'boolean'],
        ]);

        $email = $this->codeToEmail($payload['email_local'] ?? $payload['codigo']);
        $emailExists = User::query()
            ->where('id', '!=', $operator->id)
            ->where('email', $email)
            ->exists();

        if ($emailExists) {
            return response()->json(['message' => 'Já existe um operador com esse e-mail de acesso.'], 422);
        }

        $operator->name = trim($payload['nome']);
        $operator->email = $email;
        $operator->role = $payload['perfil'];
        if (! empty($payload['pin'])) {
            $operator->pin_hash = Hash::make($payload['pin']);
        }
        if (! empty($payload['password'])) {
            $operator->password = Hash::make($payload['password']);
        }
        $operator->email_verified_at = $payload['ativo'] ? ($operator->email_verified_at ?? now()) : null;
        $operator->save();

        return response()->json($this->present($operator));
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'codigo' => $this->emailToCode($user->email),
            'email_local' => $this->emailToCode($user->email),
            'nome' => $user->name,
            'perfil' => $user->role,
            'ativo' => (bool) $user->email_verified_at,
        ];
    }

    private function codeToEmail(string $code): string
    {
        return strtolower(trim($code)).'@simplespdv.local';
    }

    private function emailToCode(string $email): string
    {
        $parts = explode('@', $email, 2);
        return $parts[0] ?? $email;
    }
}
