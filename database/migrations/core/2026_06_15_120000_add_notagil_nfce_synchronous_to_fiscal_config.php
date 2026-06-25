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
            if (! Schema::hasColumn('fiscal_config', 'notagil_nfce_synchronous')) {
                $table->boolean('notagil_nfce_synchronous')->default(false)->after('notagil_operation_code_nfce');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_config')) {
            return;
        }

        Schema::table('fiscal_config', function (Blueprint $table): void {
            if (Schema::hasColumn('fiscal_config', 'notagil_nfce_synchronous')) {
                $table->dropColumn('notagil_nfce_synchronous');
            }
        });
    }
};
