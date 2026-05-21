<?php

namespace Freeline\Pdv\Contracts;

interface FiscalConfigProvider
{
    public function current(): ?array;
}
