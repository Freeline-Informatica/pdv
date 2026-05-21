<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $productTable = config('pdv.tables.products', 'products');
        $usesErpProductKey = config('pdv.mode') === 'erp';

        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('numero')->unique();
            $table->string('status')->default('finalizada');
            $table->string('document_type')->default('nfce');
            $table->string('cliente_nome')->nullable();
            $table->decimal('total_bruto', 12, 2)->default(0);
            $table->decimal('total_financeiro', 12, 2)->default(0);
            $table->decimal('juros_total', 12, 2)->default(0);
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sold_at']);
            $table->index(['document_type', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) use ($productTable, $usesErpProductKey): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            if ($usesErpProductKey) {
                $table->foreignId('product_id')->nullable()->constrained($productTable)->nullOnDelete();
            } else {
                $table->uuid('product_id')->nullable();
            }
            $table->string('produto_nome');
            $table->string('produto_codigo')->nullable();
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->string('unidade', 10)->default('UN');
            $table->decimal('valor_unitario', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            if (! $usesErpProductKey) {
                $table->foreign('product_id')->references('id')->on($productTable)->nullOnDelete();
            }
            $table->index(['sale_id', 'created_at']);
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->string('metodo_nome');
            $table->string('descricao')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->index(['sale_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
