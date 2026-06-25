<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produto')) {
            return;
        }

        DB::table('produto')
            ->select(['id', 'atributos_logisticos'])
            ->whereNotNull('atributos_logisticos')
            ->orderBy('id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $attributes = is_string($row->atributos_logisticos)
                        ? json_decode($row->atributos_logisticos, true)
                        : (array) $row->atributos_logisticos;

                    if (! is_array($attributes) || ! array_key_exists('fiscal_ncm', $attributes)) {
                        continue;
                    }

                    $digits = preg_replace('/\D+/', '', (string) $attributes['fiscal_ncm']);
                    $normalized = $digits !== '' ? $digits : null;
                    if (($attributes['fiscal_ncm'] ?? null) === $normalized) {
                        continue;
                    }

                    $attributes['fiscal_ncm'] = $normalized;

                    DB::table('produto')
                        ->where('id', $row->id)
                        ->update([
                            'atributos_logisticos' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Normalization is intentionally irreversible.
    }
};
