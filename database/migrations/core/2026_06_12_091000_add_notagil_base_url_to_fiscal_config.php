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
            if (! Schema::hasColumn('fiscal_config', 'notagil_base_url')) {
                $table->string('notagil_base_url', 2048)->nullable()->after('notagil_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_config')) {
            return;
        }

        Schema::table('fiscal_config', function (Blueprint $table): void {
            if (Schema::hasColumn('fiscal_config', 'notagil_base_url')) {
                $table->dropColumn('notagil_base_url');
            }
        });
    }
};
