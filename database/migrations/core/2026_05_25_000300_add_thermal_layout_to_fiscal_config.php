<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_config')) {
            return;
        }

        Schema::table('fiscal_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('fiscal_config', 'logo_url')) {
                $table->longText('logo_url')->nullable()->after('notagil_operation_code_nfe');
            }

            if (! Schema::hasColumn('fiscal_config', 'layout_cupom')) {
                $table->json('layout_cupom')->nullable()->after('logo_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_config')) {
            return;
        }

        Schema::table('fiscal_config', function (Blueprint $table): void {
            foreach (['layout_cupom', 'logo_url'] as $column) {
                if (Schema::hasColumn('fiscal_config', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
