<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\FiscalConfigProvider;
use Freeline\Pdv\Models\FiscalConfig;

class StandaloneFiscalConfigProvider implements FiscalConfigProvider
{
    public function current(): ?array
    {
        return FiscalConfig::query()->first()?->toArray();
    }
}
