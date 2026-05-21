<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('tipo')->default('correcao');
            $table->string('status')->default('pendente');
            $table->decimal('quantidade_atual', 12, 3);
            $table->decimal('nova_quantidade', 12, 3);
            $table->decimal('diferenca', 12, 3);
            $table->text('complemento')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('stock_adjustment_id')->nullable();
            $table->string('tipo')->default('ajuste');
            $table->string('origem')->default('ajuste_estoque');
            $table->string('referencia')->nullable();
            $table->decimal('quantidade_anterior', 12, 3);
            $table->decimal('quantidade_movimentada', 12, 3);
            $table->decimal('quantidade_atual', 12, 3);
            $table->text('descricao')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustments')->nullOnDelete();
            $table->index(['product_id', 'happened_at']);
            $table->index(['tipo', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_adjustments');
    }
};
