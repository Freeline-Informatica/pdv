<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fiscal_config')) {
            Schema::table('fiscal_config', function (Blueprint $table): void {
                if (! Schema::hasColumn('fiscal_config', 'notagil_enabled')) {
                    $table->boolean('notagil_enabled')->default(false)->after('impressao_automatica');
                }

                if (! Schema::hasColumn('fiscal_config', 'notagil_company_id')) {
                    $table->string('notagil_company_id', 80)->nullable()->after('notagil_enabled');
                }

                if (! Schema::hasColumn('fiscal_config', 'notagil_operation_code_nfce')) {
                    $table->string('notagil_operation_code_nfce', 80)->nullable()->after('notagil_company_id');
                }

                if (! Schema::hasColumn('fiscal_config', 'notagil_operation_code_nfe')) {
                    $table->string('notagil_operation_code_nfe', 80)->nullable()->after('notagil_operation_code_nfce');
                }
            });
        }

        Schema::create('sale_fiscal_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->string('document_type', 20);
            $table->string('environment', 30)->nullable();
            $table->string('series', 20)->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->string('operation_code', 80);
            $table->string('external_id', 120)->unique();
            $table->string('idempotency_key', 160)->unique();
            $table->string('status', 40)->default('pending');
            $table->string('fiscal_status', 60)->nullable();
            $table->string('operational_status', 60)->nullable();
            $table->string('access_key', 80)->nullable();
            $table->string('protocol', 80)->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('contingency_printed_at')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->index(['sale_id', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['document_type', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_fiscal_documents');

        if (Schema::hasTable('fiscal_config')) {
            Schema::table('fiscal_config', function (Blueprint $table): void {
                foreach ([
                    'notagil_operation_code_nfe',
                    'notagil_operation_code_nfce',
                    'notagil_company_id',
                    'notagil_enabled',
                ] as $column) {
                    if (Schema::hasColumn('fiscal_config', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
