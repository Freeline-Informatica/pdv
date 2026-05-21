<?php

namespace Freeline\Pdv\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface OperatorResolver
{
    public function findForLogin(string $email): ?Authenticatable;

    public function findAdminByPin(string $pin): ?Authenticatable;

    public function isAdmin(Authenticatable $operator): bool;

    public function isOperator(Authenticatable $operator): bool;
}
