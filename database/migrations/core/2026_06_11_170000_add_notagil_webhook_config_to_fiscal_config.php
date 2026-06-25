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
            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_url')) {
                $table->string('notagil_webhook_url', 2048)->nullable()->after('notagil_operation_code_nfe');
            }

            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_secret')) {
                $table->string('notagil_webhook_secret', 255)->nullable()->after('notagil_webhook_url');
            }

            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_tolerance_seconds')) {
                $table->unsignedInteger('notagil_webhook_tolerance_seconds')->nullable()->after('notagil_webhook_secret');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_config')) {
            return;
        }

        Schema::table('fiscal_config', function (Blueprint $table): void {
            foreach ([
                'notagil_webhook_tolerance_seconds',
                'notagil_webhook_secret',
                'notagil_webhook_url',
            ] as $column) {
                if (Schema::hasColumn('fiscal_config', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
