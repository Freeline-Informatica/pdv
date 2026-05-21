<?php

namespace Freeline\Pdv\Contracts;

use Illuminate\Support\Collection;

interface PaymentMethodRepository
{
    public function enabledForPdv(): Collection;
}
