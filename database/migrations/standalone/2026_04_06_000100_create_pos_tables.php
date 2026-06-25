<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('cnpj')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('inscricao_municipal')->nullable();
            $table->string('regime_tributario')->nullable();
            $table->string('cnae')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_config', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('ambiente')->default('homologacao');
            $table->string('serie_nfe')->nullable()->default('1');
            $table->string('serie_nfce')->nullable()->default('1');
            $table->string('proximo_numero_nfe')->nullable()->default('1');
            $table->string('proximo_numero_nfce')->nullable()->default('1');
            $table->string('csc')->nullable();
            $table->string('id_csc')->nullable();
            $table->boolean('emitir_nfce')->default(true);
            $table->boolean('emitir_nfe')->default(false);
            $table->boolean('impressao_automatica')->default(true);
            $table->timestamps();
        });

        Schema::create('digital_certificate', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tipo')->default('a1');
            $table->date('validade')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->string('senha_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('tipo')->default('dinheiro');
            $table->boolean('ativo')->default(true);
            $table->boolean('tef_habilitado')->default(false);
            $table->string('tef_provedor')->nullable();
            $table->string('tef_adquirente')->nullable();
            $table->integer('parcelas_max')->default(1);
            $table->decimal('parcela_minima', 10, 2)->nullable()->default(0);
            $table->decimal('taxa_debito', 5, 2)->nullable()->default(0);
            $table->decimal('taxa_credito_vista', 5, 2)->nullable()->default(0);
            $table->decimal('taxa_credito_parcelado', 5, 2)->nullable()->default(0);
            $table->integer('dias_recebimento')->nullable()->default(1);
            $table->text('observacoes')->nullable();
            $table->integer('ordem_pdv')->default(0);
            $table->boolean('permite_troco')->default(false);
            $table->boolean('permite_parcelamento')->default(false);
            $table->boolean('permite_multiplos_pagamentos')->default(true);
            $table->integer('parcelas_min')->default(1);
            $table->integer('sem_juros_ate')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->uuid('payment_method_id');
            $table->boolean('ativo')->default(true);
            $table->integer('ordem_pdv')->default(0);
            $table->integer('quantidade_parcelas')->default(1);
            $table->integer('intervalo_parcelas')->default(30);
            $table->decimal('valor_minimo_parcela', 10, 2)->nullable()->default(0);
            $table->boolean('possui_juros')->default(false);
            $table->decimal('percentual_juros', 10, 2)->nullable()->default(0);
            $table->boolean('exibir_pdv')->default(true);
            $table->timestamps();

            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->cascadeOnDelete();
        });

        Schema::create('acquirers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('cnpj')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('acquirer_card_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('acquirer_id');
            $table->string('bandeira');
            $table->decimal('taxa_debito', 10, 2)->nullable()->default(0);
            $table->decimal('taxa_credito_vista', 10, 2)->nullable()->default(0);
            $table->decimal('taxa_credito_parcelado', 10, 2)->nullable()->default(0);
            $table->integer('dias_recebimento_debito')->nullable()->default(1);
            $table->integer('dias_recebimento_credito')->nullable()->default(30);
            $table->timestamps();

            $table->foreign('acquirer_id')->references('id')->on('acquirers')->cascadeOnDelete();
        });

        Schema::create('acquirer_bank_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('acquirer_id');
            $table->string('banco');
            $table->string('agencia');
            $table->string('conta');
            $table->string('tipo_conta')->default('corrente');
            $table->string('titular')->nullable();
            $table->timestamps();

            $table->foreign('acquirer_id')->references('id')->on('acquirers')->cascadeOnDelete();
        });

        Schema::create('acquirer_terminals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('acquirer_id');
            $table->string('tipo')->default('POS');
            $table->integer('estacao')->default(1);
            $table->string('descricao')->nullable();
            $table->string('formula')->default('resto_primeira');
            $table->timestamps();

            $table->foreign('acquirer_id')->references('id')->on('acquirers')->cascadeOnDelete();
        });

        Schema::create('acquirer_terminal_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('terminal_id');
            $table->string('tipo_credito')->default('debito');
            $table->decimal('taxa_operadora', 10, 2)->nullable()->default(0);
            $table->integer('recebe_em')->nullable()->default(1);
            $table->integer('parc_sugerida')->nullable()->default(1);
            $table->boolean('ativo')->default(true);
            $table->integer('parc_inicial')->default(1);
            $table->integer('parc_final')->default(1);
            $table->timestamps();

            $table->foreign('terminal_id')->references('id')->on('acquirer_terminals')->cascadeOnDelete();
        });

        Schema::create('acquirer_tef_config', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('acquirer_id');
            $table->string('provedor');
            $table->string('terminal_id')->nullable();
            $table->string('estabelecimento_id')->nullable();
            $table->string('ip_servidor')->nullable();
            $table->string('porta_servidor')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->uuid('terminal_ref_id')->nullable();
            $table->string('tipo_integracao')->default('discado');
            $table->string('diretorio_gerenciador')->nullable();
            $table->string('diretorio_envio')->nullable();
            $table->string('diretorio_retorno')->nullable();
            $table->boolean('enviar_rede')->default(false);
            $table->boolean('enviar_cnc')->default(false);
            $table->boolean('v700')->default(false);
            $table->timestamps();

            $table->foreign('acquirer_id')->references('id')->on('acquirers')->cascadeOnDelete();
            $table->foreign('terminal_ref_id')->references('id')->on('acquirer_terminals')->cascadeOnDelete();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('codigo')->nullable();
            $table->decimal('preco_venda', 12, 2)->default(0);
            $table->decimal('preco_custo', 12, 2)->nullable()->default(0);
            $table->string('unidade')->default('UN');
            $table->decimal('estoque_atual', 12, 2)->default(0);
            $table->decimal('estoque_minimo', 12, 2)->nullable()->default(0);
            $table->uuid('category_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('acquirer_tef_config');
        Schema::dropIfExists('acquirer_terminal_rates');
        Schema::dropIfExists('acquirer_terminals');
        Schema::dropIfExists('acquirer_bank_accounts');
        Schema::dropIfExists('acquirer_card_rates');
        Schema::dropIfExists('acquirers');
        Schema::dropIfExists('payment_plans');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('digital_certificate');
        Schema::dropIfExists('fiscal_config');
        Schema::dropIfExists('company_settings');
    }
};
