<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tipo_pessoa', 20)->default('fisica');
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('nome');
            $table->string('nome_fantasia')->nullable();
            $table->string('telefone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('cep', 12)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 40)->nullable();
            $table->string('bairro')->nullable();
            $table->string('complemento')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('pais')->nullable();
            $table->string('inscricao_estadual', 40)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['nome', 'ativo']);
            $table->index('cpf_cnpj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
