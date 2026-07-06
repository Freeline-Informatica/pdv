<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\PafDav;
use Freeline\Pdv\Models\PafExternalRequisition;
use RuntimeException;

class PafSequenceService
{
    public function nextDavNumber(?string $establishmentId = null): string
    {
        $query = PafDav::query()->lockForUpdate();
        if ($establishmentId !== null && $establishmentId !== '') {
            $query->where('estabelecimento_id', $establishmentId);
        }

        $last = $query->orderByDesc('number')->value('number');
        $next = ((int) preg_replace('/\D+/', '', (string) $last)) + 1;
        if ($next > 9999999999999) {
            throw new RuntimeException('Sequência DAV excedeu 13 dígitos.');
        }

        return str_pad((string) $next, 10, '0', STR_PAD_LEFT);
    }

    public function nextCre(): string
    {
        $last = PafExternalRequisition::query()
            ->lockForUpdate()
            ->orderByDesc('cre')
            ->value('cre');

        $next = ((int) preg_replace('/\D+/', '', (string) $last)) + 1;
        if ($next > 999999999) {
            throw new RuntimeException('Sequência CRE excedeu 9 dígitos.');
        }

        return str_pad((string) $next, 9, '0', STR_PAD_LEFT);
    }
}
