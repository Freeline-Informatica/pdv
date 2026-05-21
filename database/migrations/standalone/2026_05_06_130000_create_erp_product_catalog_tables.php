<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_item_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name', 160);
            $table->text('description')->nullable();
            $table->string('item_type', 20);
            $table->string('ncm', 20)->nullable();
            $table->string('ncm_descricao', 120)->nullable();
            $table->string('cest', 20)->nullable();
            $table->string('origem_mercadoria', 30)->nullable();
            $table->string('servico_codigo', 20)->nullable();
            $table->string('cod_classe_tributo', 20)->nullable();
            $table->string('ipi_classe', 20)->nullable();
            $table->string('ipi_cod_enquadramento', 20)->nullable();
            $table->string('ipi_selo_cod', 20)->nullable();
            $table->string('cod_iat', 20)->nullable();
            $table->string('cod_ippt', 20)->nullable();
            $table->string('identity_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->string('source_type', 60)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fiscal_recebimentos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable()->index();
            $table->uuid('fornecedor_id')->nullable()->index();
            $table->uuid('operation_id')->nullable()->index();
            $table->string('documento', 20)->nullable();
            $table->string('doc_scope', 20)->nullable();
            $table->string('numero_documento', 100)->nullable();
            $table->string('serie', 20)->nullable();
            $table->string('chave_acesso', 44)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_recebimento')->nullable();
            $table->string('status_operacional', 30)->nullable();
            $table->json('payload_resumo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_ibs_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable()->index();
            $table->string('regime_empresa', 30)->nullable();
            $table->string('doc_scope', 20)->nullable();
            $table->string('item_type', 20)->nullable();
            $table->string('ncm', 20)->nullable()->index();
            $table->string('servico_codigo', 20)->nullable();
            $table->string('uf_origem', 2)->nullable();
            $table->string('uf_destino', 2)->nullable();
            $table->decimal('rate', 10, 6);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_cbs_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable()->index();
            $table->string('regime_empresa', 30)->nullable();
            $table->string('doc_scope', 20)->nullable();
            $table->string('item_type', 20)->nullable();
            $table->string('ncm', 20)->nullable()->index();
            $table->string('servico_codigo', 20)->nullable();
            $table->string('uf_origem', 2)->nullable();
            $table->string('uf_destino', 2)->nullable();
            $table->decimal('rate', 10, 6);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('produto_classificacao_mercadologica', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('codigo', 30)->unique();
            $table->string('descricao', 120);
            $table->string('descricao_reduzida', 40)->nullable();
            $table->unsignedSmallInteger('nivel')->default(1);
            $table->string('path', 255)->nullable();
            $table->integer('ordem')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('produto_classificacao_mercadologica', function (Blueprint $table): void {
            $table
                ->foreign('parent_id', 'produto_classificacao_mercadologica_parent_fk')
                ->references('id')
                ->on('produto_classificacao_mercadologica')
                ->nullOnDelete();
        });

        Schema::create('unidade_medida', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable();
            $table->string('unidade', 20);
            $table->string('descricao', 120);
            $table->string('descricao_plural', 120)->nullable();
            $table->unsignedSmallInteger('decimais')->default(0);
            $table->string('artigo', 20)->nullable();
            $table->string('codigo_fiscal', 30)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estabelecimento_id', 'unidade']);
            $table->index(['estabelecimento_id', 'codigo_fiscal']);
        });

        Schema::create('produto_familia', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('grupo_empresarial_id')->nullable();
            $table->string('codigo', 30);
            $table->string('nome', 120);
            $table->string('descricao', 255)->nullable();
            $table->string('codigo_prefixo', 30)->nullable();
            $table->string('modo_geracao_codigo', 40)->nullable();
            $table->integer('faixa_inicial')->nullable();
            $table->integer('faixa_final')->nullable();
            $table->integer('proximo_numero')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['grupo_empresarial_id', 'codigo']);
        });

        Schema::create('produto_mestre', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('grupo_empresarial_id')->nullable();
            $table->string('sku', 100)->nullable()->index();
            $table->string('descricao', 255);
            $table->string('descricao_curta', 255)->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('produto_tipo', 50)->nullable();
            $table->uuid('unidade_medida_id')->nullable();
            $table->uuid('classificacao_mercadologica_id')->nullable();
            $table->json('atributos_logisticos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unidade_medida_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->foreign('classificacao_mercadologica_id')->references('id')->on('produto_classificacao_mercadologica')->nullOnDelete();
        });

        Schema::create('produto', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('produto_mestre_id')->nullable();
            $table->uuid('fiscal_item_profile_id')->nullable();
            $table->uuid('fiscal_item_profile_entrada_id')->nullable();
            $table->uuid('fiscal_item_profile_saida_id')->nullable();
            $table->uuid('classificacao_mercadologica_id')->nullable();
            $table->uuid('unidade_medida_id')->nullable();
            $table->uuid('produto_familia_id')->nullable();
            $table->string('cod_sku', 100)->nullable();
            $table->string('codigo_operacional', 60)->nullable();
            $table->boolean('codigo_operacional_manual')->default(false);
            $table->string('descricao', 255);
            $table->string('descricao_curta', 255)->nullable();
            $table->string('produto_tipo', 50)->nullable();
            $table->string('situacao', 50)->nullable();
            $table->enum('liberado', ['sim', 'nao'])->default('sim');
            $table->string('marca', 50)->nullable();
            $table->string('palavra_chave', 100)->nullable();
            $table->boolean('permite_fracionamento')->default(false);
            $table->json('atributos_logisticos')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_mestre_id')->references('id')->on('produto_mestre')->nullOnDelete();
            $table->foreign('fiscal_item_profile_id')->references('id')->on('fiscal_item_profiles')->nullOnDelete();
            $table->foreign('fiscal_item_profile_entrada_id')->references('id')->on('fiscal_item_profiles')->nullOnDelete();
            $table->foreign('fiscal_item_profile_saida_id')->references('id')->on('fiscal_item_profiles')->nullOnDelete();
            $table->foreign('classificacao_mercadologica_id')->references('id')->on('produto_classificacao_mercadologica')->nullOnDelete();
            $table->foreign('unidade_medida_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->foreign('produto_familia_id')->references('id')->on('produto_familia')->nullOnDelete();

            $table->index('cod_sku');
            $table->index('descricao');
            $table->index(['estabelecimento_id', 'unidade_medida_id']);
            $table->index(['estabelecimento_id', 'cod_sku']);
            $table->unique(['estabelecimento_id', 'codigo_operacional']);
        });

        Schema::create('produto_preco', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->string('tipo', 50);
            $table->string('codigo', 60)->nullable();
            $table->string('canal', 50)->nullable();
            $table->decimal('valor', 15, 4)->default(0);
            $table->decimal('percentual', 5, 2)->nullable();
            $table->decimal('custo_referencial', 15, 4)->nullable();
            $table->decimal('margem', 5, 2)->nullable();
            $table->decimal('margem_preco_minimo', 5, 2)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->index(['produto_id', 'tipo', 'ativo']);
            $table->index(['produto_id', 'canal', 'ativo']);
        });

        Schema::create('produto_precificacao_regra', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->string('tipo', 30);
            $table->integer('prioridade')->default(0);
            $table->decimal('quantidade_min', 18, 6)->nullable();
            $table->integer('leve')->nullable();
            $table->integer('pague')->nullable();
            $table->decimal('multiplo', 18, 6)->nullable();
            $table->decimal('preco_unitario', 18, 6)->nullable();
            $table->decimal('preco_pacote', 18, 6)->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->index(['produto_id', 'ativo']);
            $table->index(['produto_id', 'tipo']);
            $table->index(['produto_id', 'prioridade']);
            $table->index(['produto_id', 'ativo', 'data_inicio', 'data_fim']);
        });

        Schema::create('produto_estoque', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id')->unique();
            $table->decimal('quantidade', 18, 6)->default(0);
            $table->decimal('quantidade_minima', 18, 6)->nullable();
            $table->decimal('quantidade_maxima', 18, 6)->nullable();
            $table->string('numero_lote', 255)->nullable();
            $table->boolean('reduzir_estoque')->default(true);
            $table->decimal('quantidade_minima_vendavel', 18, 6)->nullable();
            $table->decimal('quantidade_alerta', 18, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
        });

        Schema::create('conversao_unidade', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->uuid('unidade_origem_id')->nullable();
            $table->uuid('unidade_destino_id')->nullable();
            $table->string('unidade_origem', 20)->nullable();
            $table->string('unidade_destino', 20)->nullable();
            $table->decimal('fator', 18, 8);
            $table->boolean('ativo')->default(true);
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unidade_origem_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->foreign('unidade_destino_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->index(['estabelecimento_id', 'unidade_origem_id', 'unidade_destino_id']);
            $table->index(['unidade_origem', 'unidade_destino']);
            $table->index('codigo');
        });

        Schema::create('produto_apresentacao', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('unidade_medida_id')->nullable();
            $table->string('descricao_embalagem', 255)->nullable();
            $table->decimal('quantidade_unidade_base', 18, 6)->default(1);
            $table->string('tipo_apresentacao', 30)->nullable();
            $table->boolean('principal')->default(false);
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('unidade_medida_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->index(['produto_id', 'principal']);
        });

        Schema::create('produto_codigo_barras', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('produto_apresentacao_id')->nullable();
            $table->string('codigo', 30);
            $table->string('tipo_codigo', 20)->nullable();
            $table->boolean('principal')->default(false);
            $table->string('informacoes_complementares', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('produto_apresentacao_id')->references('id')->on('produto_apresentacao')->nullOnDelete();
            $table->index('codigo');
            $table->index(['produto_id', 'principal']);
        });

        Schema::create('produto_composto', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('produto_componente_id');
            $table->decimal('quantidade', 18, 6)->default(1);
            $table->string('perfil_tributario', 60)->nullable();
            $table->string('contexto', 20)->nullable();
            $table->uuid('unidade_medida_id')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->integer('ordem')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('produto_componente_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('unidade_medida_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->unique(['produto_id', 'produto_componente_id']);
        });

        Schema::create('produto_fornecedor', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('fornecedor_id')->nullable();
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('unidade_medida_compra_id')->nullable();
            $table->uuid('produto_apresentacao_id')->nullable();
            $table->uuid('fiscal_item_profile_entrada_id')->nullable();
            $table->string('embalagem_compra_descricao', 120)->nullable();
            $table->string('codigo_produto_fornecedor', 100)->nullable();
            $table->string('codigo_barras_fornecedor', 30)->nullable();
            $table->decimal('fator_conversao_compra', 18, 8)->nullable();
            $table->integer('lead_time_dias')->nullable();
            $table->decimal('lote_minimo', 18, 6)->nullable();
            $table->decimal('multiplo_compra', 18, 6)->nullable();
            $table->decimal('quantidade_minima_compra', 18, 6)->nullable();
            $table->decimal('custo_ultima_compra', 18, 6)->nullable();
            $table->decimal('custo_negociado', 18, 6)->nullable();
            $table->timestamp('ultimo_orcado_em')->nullable();
            $table->boolean('fornecedor_preferencial')->default(false);
            $table->boolean('homologado')->default(true);
            $table->boolean('ativo')->default(true);
            $table->date('inicio_vigencia')->nullable();
            $table->date('fim_vigencia')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('fornecedor_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('unidade_medida_compra_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->foreign('produto_apresentacao_id')->references('id')->on('produto_apresentacao')->nullOnDelete();
            $table->foreign('fiscal_item_profile_entrada_id')->references('id')->on('fiscal_item_profiles')->nullOnDelete();
            $table->index(['produto_id', 'estabelecimento_id']);
            $table->index(['fornecedor_id', 'estabelecimento_id']);
            $table->index(['produto_id', 'fornecedor_preferencial']);
            $table->index(['fornecedor_id', 'codigo_produto_fornecedor']);
            $table->index(['fornecedor_id', 'codigo_barras_fornecedor']);
            $table->index(['produto_id', 'produto_apresentacao_id']);
            $table->index(['produto_id', 'fornecedor_id', 'homologado', 'deleted_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX produto_fornecedor_preferencial_ativo_unique ON produto_fornecedor (produto_id, estabelecimento_id) WHERE fornecedor_preferencial = true AND ativo = true AND deleted_at IS NULL"
            );
        }

        Schema::create('produto_politica_ressuprimento', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('fornecedor_preferencial_id')->nullable();
            $table->string('metodo_calculo', 30)->nullable();
            $table->decimal('estoque_seguranca', 18, 6)->nullable();
            $table->decimal('ponto_pedido', 18, 6)->nullable();
            $table->integer('cobertura_dias')->nullable();
            $table->decimal('estoque_minimo_operacional', 18, 6)->nullable();
            $table->decimal('estoque_maximo_operacional', 18, 6)->nullable();
            $table->integer('revisao_periodica_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('fornecedor_preferencial_id')->references('id')->on('produto_fornecedor')->nullOnDelete();
        });

        Schema::create('estoque_local', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('estoque_endereco', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('estoque_local_id')->nullable();
            $table->string('codigo', 60)->nullable();
            $table->string('descricao', 120)->nullable();
            $table->string('rua', 30)->nullable();
            $table->string('modulo', 30)->nullable();
            $table->string('prateleira', 30)->nullable();
            $table->string('nivel', 30)->nullable();
            $table->string('box', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('estoque_local_id')->references('id')->on('estoque_local')->nullOnDelete();
        });

        Schema::create('produto_lote', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('estabelecimento_id')->nullable();
            $table->string('numero_lote', 255);
            $table->date('data_fabricacao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
        });

        Schema::create('produto_saldo_estoque', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('estoque_local_id')->nullable();
            $table->uuid('estoque_endereco_id')->nullable();
            $table->uuid('produto_lote_id')->nullable();
            $table->decimal('quantidade', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('estoque_local_id')->references('id')->on('estoque_local')->nullOnDelete();
            $table->foreign('estoque_endereco_id')->references('id')->on('estoque_endereco')->nullOnDelete();
            $table->foreign('produto_lote_id')->references('id')->on('produto_lote')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX produto_saldo_estoque_slot_unique ON produto_saldo_estoque (produto_id, estoque_local_id, COALESCE(estoque_endereco_id::text, ''), COALESCE(produto_lote_id::text, ''))"
            );
        } else {
            Schema::table('produto_saldo_estoque', function (Blueprint $table): void {
                $table->unique(['produto_id', 'estoque_local_id', 'estoque_endereco_id', 'produto_lote_id'], 'produto_saldo_estoque_slot_unique');
            });
        }

        Schema::create('produto_movimentacao_estoque', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('produto_estoque_id')->nullable();
            $table->uuid('estabelecimento_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_movimento', 30);
            $table->string('origem_tipo', 60)->nullable();
            $table->uuid('origem_id')->nullable();
            $table->decimal('quantidade', 18, 6);
            $table->decimal('saldo_anterior', 18, 6)->nullable();
            $table->decimal('saldo_posterior', 18, 6)->nullable();
            $table->decimal('custo_unitario', 18, 6)->nullable();
            $table->string('numero_lote', 255)->nullable();
            $table->date('data_validade')->nullable();
            $table->string('localizacao', 100)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamp('data_movimento')->nullable();
            $table->uuid('estoque_local_id')->nullable();
            $table->uuid('estoque_endereco_id')->nullable();
            $table->uuid('produto_lote_id')->nullable();
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('produto_estoque_id')->references('id')->on('produto_estoque')->nullOnDelete();
            $table->foreign('estoque_local_id')->references('id')->on('estoque_local')->nullOnDelete();
            $table->foreign('estoque_endereco_id')->references('id')->on('estoque_endereco')->nullOnDelete();
            $table->foreign('produto_lote_id')->references('id')->on('produto_lote')->nullOnDelete();
        });

        Schema::create('produto_historico_compra', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('fornecedor_id')->nullable();
            $table->uuid('produto_fornecedor_id')->nullable();
            $table->uuid('fiscal_recebimento_id')->nullable();
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('unidade_medida_compra_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('documento_numero', 100)->nullable();
            $table->date('data_compra')->nullable();
            $table->date('data_recebimento')->nullable();
            $table->decimal('quantidade', 18, 6)->nullable();
            $table->decimal('custo_unitario', 18, 6)->nullable();
            $table->decimal('custo_total', 18, 6)->nullable();
            $table->string('moeda', 3)->nullable();
            $table->integer('prazo_entrega_dias')->nullable();
            $table->string('status', 40)->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->uuid('origem_id')->nullable();
            $table->uuid('estoque_local_id')->nullable();
            $table->uuid('estoque_endereco_id')->nullable();
            $table->string('numero_lote', 255)->nullable();
            $table->date('data_fabricacao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('fornecedor_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('produto_fornecedor_id')->references('id')->on('produto_fornecedor')->nullOnDelete();
            $table->foreign('fiscal_recebimento_id')->references('id')->on('fiscal_recebimentos')->nullOnDelete();
            $table->foreign('unidade_medida_compra_id')->references('id')->on('unidade_medida')->nullOnDelete();
            $table->foreign('estoque_local_id')->references('id')->on('estoque_local')->nullOnDelete();
            $table->foreign('estoque_endereco_id')->references('id')->on('estoque_endereco')->nullOnDelete();
        });

        Schema::create('produto_camada_custo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('produto_historico_compra_id')->nullable();
            $table->uuid('produto_lote_id')->nullable();
            $table->decimal('quantidade_entrada', 18, 6);
            $table->decimal('quantidade_disponivel', 18, 6);
            $table->decimal('custo_unitario', 18, 6);
            $table->date('data_entrada')->nullable();
            $table->string('origem_tipo', 60)->nullable();
            $table->uuid('origem_id')->nullable();
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('produto_historico_compra_id')->references('id')->on('produto_historico_compra')->nullOnDelete();
            $table->foreign('produto_lote_id')->references('id')->on('produto_lote')->nullOnDelete();
        });

        Schema::create('produto_consumo_camada_custo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('produto_camada_custo_id')->nullable();
            $table->uuid('produto_movimentacao_estoque_id')->nullable();
            $table->decimal('quantidade_consumida', 18, 6);
            $table->decimal('custo_unitario', 18, 6);
            $table->decimal('custo_total', 18, 6);
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            $table->foreign('produto_camada_custo_id')->references('id')->on('produto_camada_custo')->nullOnDelete();
            $table->foreign('produto_movimentacao_estoque_id')->references('id')->on('produto_movimentacao_estoque')->nullOnDelete();
        });

        Schema::create('produto_custo_historico', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id')->nullable();
            $table->uuid('produto_mestre_id')->nullable();
            $table->uuid('estabelecimento_id')->nullable();
            $table->uuid('fornecedor_id')->nullable();
            $table->uuid('produto_fornecedor_id')->nullable();
            $table->uuid('fiscal_recebimento_id')->nullable();
            $table->uuid('produto_historico_compra_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->uuid('origem_id')->nullable();
            $table->date('data_referencia')->nullable();
            $table->decimal('custo_unitario', 18, 6)->nullable();
            $table->decimal('quantidade_referencia', 18, 6)->nullable();
            $table->string('moeda', 3)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->nullOnDelete();
            $table->foreign('produto_mestre_id')->references('id')->on('produto_mestre')->nullOnDelete();
            $table->foreign('fornecedor_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('produto_fornecedor_id')->references('id')->on('produto_fornecedor')->nullOnDelete();
            $table->foreign('fiscal_recebimento_id')->references('id')->on('fiscal_recebimentos')->nullOnDelete();
            $table->foreign('produto_historico_compra_id')->references('id')->on('produto_historico_compra')->nullOnDelete();
        });

        Schema::create('produto_auditoria', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('produto_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidade_tipo', 60)->nullable();
            $table->uuid('entidade_id')->nullable();
            $table->string('evento', 80);
            $table->json('alteracoes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produto')->nullOnDelete();
            $table->index(['entidade_tipo', 'entidade_id']);
            $table->index('evento');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS produto_fornecedor_preferencial_ativo_unique');
            DB::statement('DROP INDEX IF EXISTS produto_saldo_estoque_slot_unique');
        }

        Schema::dropIfExists('produto_auditoria');
        Schema::dropIfExists('produto_custo_historico');
        Schema::dropIfExists('produto_consumo_camada_custo');
        Schema::dropIfExists('produto_camada_custo');
        Schema::dropIfExists('produto_historico_compra');
        Schema::dropIfExists('produto_movimentacao_estoque');
        Schema::dropIfExists('produto_saldo_estoque');
        Schema::dropIfExists('produto_lote');
        Schema::dropIfExists('estoque_endereco');
        Schema::dropIfExists('estoque_local');
        Schema::dropIfExists('produto_politica_ressuprimento');
        Schema::dropIfExists('produto_fornecedor');
        Schema::dropIfExists('produto_composto');
        Schema::dropIfExists('produto_codigo_barras');
        Schema::dropIfExists('produto_apresentacao');
        Schema::dropIfExists('conversao_unidade');
        Schema::dropIfExists('produto_estoque');
        Schema::dropIfExists('produto_precificacao_regra');
        Schema::dropIfExists('produto_preco');
        Schema::dropIfExists('produto');
        Schema::dropIfExists('produto_mestre');
        Schema::dropIfExists('produto_familia');
        Schema::dropIfExists('unidade_medida');
        Schema::dropIfExists('produto_classificacao_mercadologica');
        Schema::dropIfExists('tax_cbs_rates');
        Schema::dropIfExists('tax_ibs_rates');
        Schema::dropIfExists('fiscal_recebimentos');
        Schema::dropIfExists('fiscal_item_profiles');
    }
};
