<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_tokens', 'grupo_empresarial_id')) {
                $table->string('grupo_empresarial_id', 80)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('api_tokens', 'estabelecimento_id')) {
                $table->string('estabelecimento_id', 80)->nullable()->after('grupo_empresarial_id');
            }

            if (! Schema::hasIndex('api_tokens', 'api_tokens_tenant_idx')) {
                $table->index(['grupo_empresarial_id', 'estabelecimento_id'], 'api_tokens_tenant_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            if (Schema::hasIndex('api_tokens', 'api_tokens_tenant_idx')) {
                $table->dropIndex('api_tokens_tenant_idx');
            }

            if (Schema::hasColumn('api_tokens', 'grupo_empresarial_id')) {
                $table->dropColumn('grupo_empresarial_id');
            }

            if (Schema::hasColumn('api_tokens', 'estabelecimento_id')) {
                $table->dropColumn('estabelecimento_id');
            }
        });
    }
};
