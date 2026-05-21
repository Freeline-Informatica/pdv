<?php

namespace Freeline\Pdv\Contracts;

use Illuminate\Support\Collection;

interface ProductCatalogRepository
{
    public function categories(): Collection;

    public function search(array $filters = []): Collection;

    public function find(string|int $id): ?array;
}
