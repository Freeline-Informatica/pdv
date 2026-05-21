<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produto_classificacao_mercadologica', 'parametros_observacoes')) {
            return;
        }

        Schema::table('produto_classificacao_mercadologica', function (Blueprint $table): void {
            $table->json('parametros_observacoes')->nullable()->after('descricao_reduzida');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('produto_classificacao_mercadologica', 'parametros_observacoes')) {
            return;
        }

        Schema::table('produto_classificacao_mercadologica', function (Blueprint $table): void {
            $table->dropColumn('parametros_observacoes');
        });
    }
};
