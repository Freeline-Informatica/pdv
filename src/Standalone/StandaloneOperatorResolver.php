<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\OperatorResolver;
use Freeline\Pdv\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class StandaloneOperatorResolver implements OperatorResolver
{
    public function findForLogin(string $email): ?Authenticatable
    {
        return User::query()->where('email', $email)->first();
    }

    public function findAdminByPin(string $pin): ?Authenticatable
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereNotNull('pin_hash')
            ->get()
            ->first(fn (User $user): bool => Hash::check($pin, $user->pin_hash));
    }

    public function isAdmin(Authenticatable $operator): bool
    {
        return method_exists($operator, 'isAdmin') ? $operator->isAdmin() : false;
    }

    public function isOperator(Authenticatable $operator): bool
    {
        return method_exists($operator, 'isOperator') ? $operator->isOperator() : false;
    }
}
