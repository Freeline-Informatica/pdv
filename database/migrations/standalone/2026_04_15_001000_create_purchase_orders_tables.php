<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('numero')->unique();
            $table->uuid('supplier_id');
            $table->date('data_compra');
            $table->string('filial')->nullable();
            $table->string('status')->default('aberto');
            $table->text('observacoes')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('total_quantity', 12, 3)->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->index(['status', 'data_compra']);
            $table->index(['supplier_id', 'data_compra']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('product_id')->nullable();
            $table->string('produto_nome');
            $table->string('produto_codigo')->nullable();
            $table->decimal('quantidade', 12, 3)->default(0);
            $table->decimal('quantidade_recebida', 12, 3)->default(0);
            $table->decimal('custo_unitario', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index(['purchase_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};

