<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    public function __construct(
        private readonly CompanyContextResolver $companyContext,
    ) {
    }

    public function record(
        ?Authenticatable $operator,
        string $actionKey,
        string $actionLabel,
        string $entity,
        ?string $details = null,
        ?string $entityId = null,
        array $meta = [],
    ): void {
        try {
            $scope = $this->currentScope();

            AuditLog::query()->create([
                'grupo_empresarial_id' => $scope['grupo_id'],
                'estabelecimento_id' => $scope['estabelecimento_id'],
                'action_key' => $actionKey,
                'action_label' => $actionLabel,
                'entity' => $entity,
                'entity_id' => $entityId,
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name,
                'operator_code' => $this->resolveUserCode($operator),
                'operator_role' => $this->resolveUserRole($operator),
                'details' => $details,
                'meta' => $meta ?: null,
            ]);
        } catch (Throwable) {
            // Auditoria nunca pode travar o fluxo principal do sistema.
        }
    }

    public function resolveUserCode(?Authenticatable $user): ?string
    {
        $email = $user?->getAuthIdentifierName() === 'email'
            ? $user?->getAuthIdentifier()
            : ($user?->email ?? null);

        if (! is_string($email) || $email === '') {
            return null;
        }

        return Str::before($email, '@');
    }

    public function resolveUserRole(?Authenticatable $user): ?string
    {
        if (! $user) {
            return null;
        }

        if (method_exists($user, 'getAttribute')) {
            return $user->getAttribute('role')
                ?? $user->getAttribute('perfil')
                ?? (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? 'admin' : null)
                ?? (method_exists($user, 'canAccessPdv') && $user->canAccessPdv() ? 'operador' : null);
        }

        return $user->role
            ?? $user->perfil
            ?? (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? 'admin' : null)
            ?? (method_exists($user, 'canAccessPdv') && $user->canAccessPdv() ? 'operador' : null);
    }

    private function currentScope(): array
    {
        if (config('pdv.mode') !== 'erp') {
            return [
                'grupo_id' => null,
                'estabelecimento_id' => null,
            ];
        }

        $groupId = $this->companyContext->currentGroupId();
        $establishmentId = $this->companyContext->currentEstablishmentId();

        return [
            'grupo_id' => $groupId ? (string) $groupId : null,
            'estabelecimento_id' => $establishmentId ? (string) $establishmentId : null,
        ];
    }
}
