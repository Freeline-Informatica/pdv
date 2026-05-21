<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('terminal_nome');
            $table->string('terminal_codigo', 30);
            $table->string('status')->default('aberto');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('cash_received_amount', 12, 2)->default(0);
            $table->decimal('sangria_amount', 12, 2)->default(0);
            $table->decimal('suprimento_amount', 12, 2)->default(0);
            $table->decimal('expected_amount', 12, 2)->default(0);
            $table->decimal('counted_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['terminal_codigo', 'status']);
        });

        Schema::create('cash_register_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cash_register_session_id');
            $table->string('type', 40);
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamp('happened_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('cash_register_session_id', 'cash_register_movements_session_fk')
                ->references('id')
                ->on('cash_register_sessions')
                ->cascadeOnDelete();
            $table->index(['cash_register_session_id', 'happened_at'], 'cash_register_movements_session_happened_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
