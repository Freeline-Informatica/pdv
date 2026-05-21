<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_inventories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status')->default('aberto');
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('submitted_adjustments_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('stock_inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('stock_inventory_id');
            $table->uuid('product_id');
            $table->decimal('quantidade_sistema', 12, 3);
            $table->decimal('quantidade_contada', 12, 3)->nullable();
            $table->decimal('diferenca', 12, 3)->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->foreign('stock_inventory_id')->references('id')->on('stock_inventories')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['stock_inventory_id', 'product_id']);
            $table->index(['stock_inventory_id', 'saved_at']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->uuid('stock_inventory_id')->nullable()->after('product_id');
            $table->foreign('stock_inventory_id')->references('id')->on('stock_inventories')->nullOnDelete();
            $table->index(['stock_inventory_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropForeign(['stock_inventory_id']);
            $table->dropIndex(['stock_inventory_id', 'created_at']);
            $table->dropColumn('stock_inventory_id');
        });

        Schema::dropIfExists('stock_inventory_items');
        Schema::dropIfExists('stock_inventories');
    }
};
