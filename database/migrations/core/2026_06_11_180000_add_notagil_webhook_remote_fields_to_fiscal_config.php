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
            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_id')) {
                $table->string('notagil_webhook_id', 80)->nullable()->after('notagil_webhook_tolerance_seconds');
            }

            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_status')) {
                $table->string('notagil_webhook_status', 40)->nullable()->after('notagil_webhook_id');
            }

            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_last_synced_at')) {
                $table->timestamp('notagil_webhook_last_synced_at')->nullable()->after('notagil_webhook_status');
            }

            if (! Schema::hasColumn('fiscal_config', 'notagil_webhook_last_error')) {
                $table->text('notagil_webhook_last_error')->nullable()->after('notagil_webhook_last_synced_at');
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
                'notagil_webhook_last_error',
                'notagil_webhook_last_synced_at',
                'notagil_webhook_status',
                'notagil_webhook_id',
            ] as $column) {
                if (Schema::hasColumn('fiscal_config', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
