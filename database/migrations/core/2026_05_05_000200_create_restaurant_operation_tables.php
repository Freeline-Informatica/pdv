<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $productTable = config('pdv.tables.products', 'products');
        $usesErpProductKey = config('pdv.mode') === 'erp';

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('restaurant_fichas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('table_id');
            $table->string('code', 30)->unique();
            $table->string('customer_name', 120)->nullable();
            $table->boolean('is_random_customer')->default(true);
            $table->string('waiter_name', 120)->nullable();
            $table->string('status', 20)->default('opened');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('table_id')->references('id')->on('restaurant_tables')->cascadeOnDelete();
            $table->index(['table_id', 'status']);
        });

        Schema::create('restaurant_production_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('table_id');
            $table->uuid('ficha_id');
            $table->string('sector', 20)->default('cozinha');
            $table->string('status', 20)->default('novo');
            $table->string('waiter_name', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('table_id')->references('id')->on('restaurant_tables')->cascadeOnDelete();
            $table->foreign('ficha_id')->references('id')->on('restaurant_fichas')->cascadeOnDelete();
            $table->index(['sector', 'status']);
            $table->index(['ficha_id', 'status']);
        });

        Schema::create('restaurant_production_ticket_items', function (Blueprint $table) use ($productTable, $usesErpProductKey): void {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            if ($usesErpProductKey) {
                $table->foreignId('product_id')->nullable()->constrained($productTable)->nullOnDelete();
            } else {
                $table->uuid('product_id')->nullable();
            }
            $table->string('product_name', 160);
            $table->string('product_code', 60)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->text('observation')->nullable();
            $table->text('selected_options')->nullable();
            $table->text('removed_ingredients')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('restaurant_production_tickets')->cascadeOnDelete();
            if (! $usesErpProductKey) {
                $table->foreign('product_id')->references('id')->on($productTable)->nullOnDelete();
            }
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_production_ticket_items');
        Schema::dropIfExists('restaurant_production_tickets');
        Schema::dropIfExists('restaurant_fichas');
        Schema::dropIfExists('restaurant_tables');
    }
};
