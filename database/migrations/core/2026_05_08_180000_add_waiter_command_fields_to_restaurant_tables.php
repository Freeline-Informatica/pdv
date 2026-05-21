<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_fichas', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_fichas', 'observation')) {
                $table->text('observation')->nullable()->after('waiter_name');
            }
            if (! Schema::hasColumn('restaurant_fichas', 'closing_requested_at')) {
                $table->timestamp('closing_requested_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('restaurant_fichas', 'closing_requested_by')) {
                $table->string('closing_requested_by', 120)->nullable()->after('closing_requested_at');
            }
        });

        Schema::table('restaurant_production_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_production_tickets', 'order_observation')) {
                $table->text('order_observation')->nullable()->after('waiter_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_production_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_production_tickets', 'order_observation')) {
                $table->dropColumn('order_observation');
            }
        });

        Schema::table('restaurant_fichas', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_fichas', 'closing_requested_by')) {
                $table->dropColumn('closing_requested_by');
            }
            if (Schema::hasColumn('restaurant_fichas', 'closing_requested_at')) {
                $table->dropColumn('closing_requested_at');
            }
            if (Schema::hasColumn('restaurant_fichas', 'observation')) {
                $table->dropColumn('observation');
            }
        });
    }
};
