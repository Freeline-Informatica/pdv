<?php

namespace Freeline\Pdv\Contracts;

interface CompanyContextResolver
{
    public function current(): ?array;

    public function currentCompanyId(): string|int|null;

    public function currentEstablishmentId(): string|int|null;

    public function currentGroupId(): string|int|null;
}
