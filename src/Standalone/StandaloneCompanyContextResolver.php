<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Models\CompanySetting;

class StandaloneCompanyContextResolver implements CompanyContextResolver
{
    public function current(): ?array
    {
        return CompanySetting::query()->first()?->toArray();
    }

    public function currentCompanyId(): string|int|null
    {
        return null;
    }

    public function currentEstablishmentId(): string|int|null
    {
        return null;
    }

    public function currentGroupId(): string|int|null
    {
        return null;
    }
}
