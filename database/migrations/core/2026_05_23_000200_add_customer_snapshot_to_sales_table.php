<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'customer_snapshot')) {
                $table->json('customer_snapshot')->nullable()->after('cliente_nome');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'customer_snapshot')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('customer_snapshot');
        });
    }
};
