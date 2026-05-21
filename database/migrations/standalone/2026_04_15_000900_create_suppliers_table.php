<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('documento')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('endereco')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['nome', 'ativo']);
            $table->index('documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

