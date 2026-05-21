<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            if (Schema::hasIndex('pos_terminals', 'pos_terminals_identificador_unique')) {
                $table->dropUnique('pos_terminals_identificador_unique');
            }

            if (! Schema::hasColumn('pos_terminals', 'grupo_empresarial_id')) {
                $table->string('grupo_empresarial_id', 80)->nullable()->after('id');
            }

            if (! Schema::hasColumn('pos_terminals', 'estabelecimento_id')) {
                $table->string('estabelecimento_id', 80)->nullable()->after('grupo_empresarial_id');
            }

            if (! Schema::hasIndex('pos_terminals', 'pos_terminals_estab_identificador_unique')) {
                $table->unique(['estabelecimento_id', 'identificador'], 'pos_terminals_estab_identificador_unique');
            }

            if (! Schema::hasIndex('pos_terminals', 'pos_terminals_tenant_idx')) {
                $table->index(['grupo_empresarial_id', 'estabelecimento_id'], 'pos_terminals_tenant_idx');
            }
        });

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_register_sessions', 'grupo_empresarial_id')) {
                $table->string('grupo_empresarial_id', 80)->nullable()->after('id');
            }

            if (! Schema::hasColumn('cash_register_sessions', 'estabelecimento_id')) {
                $table->string('estabelecimento_id', 80)->nullable()->after('grupo_empresarial_id');
            }

            if (! Schema::hasIndex('cash_register_sessions', 'cash_register_sessions_tenant_status_idx')) {
                $table->index(['grupo_empresarial_id', 'estabelecimento_id', 'status'], 'cash_register_sessions_tenant_status_idx');
            }
        });

        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'grupo_empresarial_id')) {
                $table->string('grupo_empresarial_id', 80)->nullable()->after('id');
            }

            if (! Schema::hasColumn('sales', 'estabelecimento_id')) {
                $table->string('estabelecimento_id', 80)->nullable()->after('grupo_empresarial_id');
            }

            if (! Schema::hasIndex('sales', 'sales_tenant_status_idx')) {
                $table->index(['grupo_empresarial_id', 'estabelecimento_id', 'status'], 'sales_tenant_status_idx');
            }

            if (! Schema::hasIndex('sales', 'sales_estab_sold_at_idx')) {
                $table->index(['estabelecimento_id', 'sold_at'], 'sales_estab_sold_at_idx');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'grupo_empresarial_id')) {
                $table->string('grupo_empresarial_id', 80)->nullable()->after('id');
            }

            if (! Schema::hasColumn('audit_logs', 'estabelecimento_id')) {
                $table->string('estabelecimento_id', 80)->nullable()->after('grupo_empresarial_id');
            }

            if (! Schema::hasIndex('audit_logs', 'audit_logs_tenant_created_idx')) {
                $table->index(['grupo_empresarial_id', 'estabelecimento_id', 'created_at'], 'audit_logs_tenant_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasIndex('audit_logs', 'audit_logs_tenant_created_idx')) {
                $table->dropIndex('audit_logs_tenant_created_idx');
            }

            if (Schema::hasColumn('audit_logs', 'grupo_empresarial_id')) {
                $table->dropColumn('grupo_empresarial_id');
            }

            if (Schema::hasColumn('audit_logs', 'estabelecimento_id')) {
                $table->dropColumn('estabelecimento_id');
            }
        });

        Schema::table('sales', function (Blueprint $table): void {
            if (Schema::hasIndex('sales', 'sales_tenant_status_idx')) {
                $table->dropIndex('sales_tenant_status_idx');
            }

            if (Schema::hasIndex('sales', 'sales_estab_sold_at_idx')) {
                $table->dropIndex('sales_estab_sold_at_idx');
            }

            if (Schema::hasColumn('sales', 'grupo_empresarial_id')) {
                $table->dropColumn('grupo_empresarial_id');
            }

            if (Schema::hasColumn('sales', 'estabelecimento_id')) {
                $table->dropColumn('estabelecimento_id');
            }
        });

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            if (Schema::hasIndex('cash_register_sessions', 'cash_register_sessions_tenant_status_idx')) {
                $table->dropIndex('cash_register_sessions_tenant_status_idx');
            }

            if (Schema::hasColumn('cash_register_sessions', 'grupo_empresarial_id')) {
                $table->dropColumn('grupo_empresarial_id');
            }

            if (Schema::hasColumn('cash_register_sessions', 'estabelecimento_id')) {
                $table->dropColumn('estabelecimento_id');
            }
        });

        Schema::table('pos_terminals', function (Blueprint $table): void {
            if (Schema::hasIndex('pos_terminals', 'pos_terminals_estab_identificador_unique')) {
                $table->dropUnique('pos_terminals_estab_identificador_unique');
            }

            if (Schema::hasIndex('pos_terminals', 'pos_terminals_tenant_idx')) {
                $table->dropIndex('pos_terminals_tenant_idx');
            }

            if (Schema::hasColumn('pos_terminals', 'grupo_empresarial_id')) {
                $table->dropColumn('grupo_empresarial_id');
            }

            if (Schema::hasColumn('pos_terminals', 'estabelecimento_id')) {
                $table->dropColumn('estabelecimento_id');
            }

            if (! Schema::hasIndex('pos_terminals', 'pos_terminals_identificador_unique')) {
                $table->unique('identificador', 'pos_terminals_identificador_unique');
            }
        });
    }
};
