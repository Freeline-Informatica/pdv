<?php

namespace Freeline\Pdv\Contracts;

interface StockMovementService
{
    public function decrease(string|int $productId, float $quantity, array $context = []): void;

    public function increase(string|int $productId, float $quantity, array $context = []): void;
}
