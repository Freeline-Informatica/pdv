<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\PaymentMethodRepository;
use Freeline\Pdv\Models\PaymentMethod;
use Illuminate\Support\Collection;

class StandalonePaymentMethodRepository implements PaymentMethodRepository
{
    public function enabledForPdv(): Collection
    {
        return PaymentMethod::query()
            ->where('ativo', true)
            ->orderBy('ordem_pdv')
            ->get();
    }
}
