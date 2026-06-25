<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\OperatorResolver;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\ApiToken;
use Freeline\Pdv\Services\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OperatorResolver $operators,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->operators->findForLogin($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email ou senha inválidos.'], 422);
        }

        $plainToken = Str::random(64);

        $tokenPayload = [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(7),
        ];

        $session = $request->hasSession() ? $request->session() : null;

        if (Schema::hasColumn('api_tokens', 'grupo_empresarial_id')) {
            $tokenPayload['grupo_empresarial_id'] = $session?->get('grupo_empresarial_id');
        }

        if (Schema::hasColumn('api_tokens', 'estabelecimento_id')) {
            $tokenPayload['estabelecimento_id'] = $session?->get('estabelecimento_id');
        }

        ApiToken::create($tokenPayload);

        $this->auditLogger->record(
            operator: $user,
            actionKey: 'auth.login',
            actionLabel: 'Login Operador',
            entity: 'Operador',
            details: implode(' · ', array_filter([
                'Operador: '.$user->name,
                'Código: '.$this->auditLogger->resolveUserCode($user),
                'Perfil: '.$this->operatorRole($user),
            ])),
            entityId: (string) $user->id,
            meta: [
                'method' => 'POST',
                'path' => 'auth/login',
            ],
        );

        return response()->json([
            'token' => $plainToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->operatorRole($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $this->operatorRole($user),
        ]);
    }

    public function authorizeSettings(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $token = $request->attributes->get('api_token');

        if (! $this->operators->isOperator($currentUser)) {
            if ($this->operators->isAdmin($currentUser)) {
                return response()->json([
                    'message' => 'Usuário administrador já possui acesso às configurações.',
                    'authorized' => true,
                ]);
            }

            return response()->json(['message' => 'Perfil sem permissão para solicitar acesso.'], 403);
        }

        $data = $request->validate([
            'admin_email' => ['nullable', 'email', 'required_without:admin_pin'],
            'admin_password' => ['nullable', 'string', 'required_with:admin_email'],
            'admin_pin' => ['nullable', 'string', 'digits:6', 'required_without:admin_email'],
        ]);

        $authorizedAdmin = null;

        if (! empty($data['admin_pin'])) {
            $authorizedAdmin = $this->operators->findAdminByPin($data['admin_pin']);
        } elseif (! empty($data['admin_email']) && ! empty($data['admin_password'])) {
            $admin = $this->operators->findForLogin($data['admin_email']);

            if ($admin && $this->operators->isAdmin($admin) && Hash::check($data['admin_password'], $admin->password)) {
                $authorizedAdmin = $admin;
            }
        }

        if (! $authorizedAdmin) {
            return response()->json(['message' => 'Credenciais de administrador inválidas.'], 422);
        }

        $settingsAccessKey = Str::random(40);
        $expiresAt = Carbon::now()->addMinutes(15);

        $token->forceFill([
            'settings_access_hash' => hash('sha256', $settingsAccessKey),
            'settings_access_expires_at' => $expiresAt,
            'settings_access_granted_by_user_id' => $authorizedAdmin->id,
        ])->save();

        return response()->json([
            'message' => 'Acesso autorizado pelo administrador.',
            'settings_access_key' => $settingsAccessKey,
            'expires_at' => $expiresAt->toIso8601String(),
            'authorized_by' => [
                'id' => $authorizedAdmin->id,
                'name' => $authorizedAdmin->name,
            ],
        ]);
    }

    public function authorizeCancel(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        if (! $this->operators->isOperator($currentUser)) {
            if ($this->operators->isAdmin($currentUser)) {
                return response()->json([
                    'message' => 'Usuário administrador já possui acesso ao menu de cancelamento.',
                    'authorized' => true,
                ]);
            }

            return response()->json(['message' => 'Perfil sem permissão para solicitar acesso.'], 403);
        }

        $data = $request->validate([
            'admin_email' => ['nullable', 'email', 'required_without:admin_pin'],
            'admin_password' => ['nullable', 'string', 'required_with:admin_email'],
            'admin_pin' => ['nullable', 'string', 'digits:6', 'required_without:admin_email'],
        ]);

        $authorizedAdmin = null;

        if (! empty($data['admin_pin'])) {
            $authorizedAdmin = $this->operators->findAdminByPin($data['admin_pin']);
        } elseif (! empty($data['admin_email']) && ! empty($data['admin_password'])) {
            $admin = $this->operators->findForLogin($data['admin_email']);

            if ($admin && $this->operators->isAdmin($admin) && Hash::check($data['admin_password'], $admin->password)) {
                $authorizedAdmin = $admin;
            }
        }

        if (! $authorizedAdmin) {
            return response()->json(['message' => 'Credenciais de administrador inválidas.'], 422);
        }

        return response()->json([
            'message' => 'Cancelamento autorizado pelo administrador.',
            'authorized_by' => [
                'id' => $authorizedAdmin->id,
                'name' => $authorizedAdmin->name,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    private function operatorRole(?Authenticatable $operator): ?string
    {
        return $this->auditLogger->resolveUserRole($operator);
    }
}
