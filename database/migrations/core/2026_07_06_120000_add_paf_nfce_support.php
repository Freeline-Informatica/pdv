<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('fiscal_config', 'paf_enabled')) {
                $table->boolean('paf_enabled')->default(false);
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_app_name')) {
                $table->string('paf_app_name')->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_app_version')) {
                $table->string('paf_app_version', 20)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_database_architecture')) {
                $table->string('paf_database_architecture', 40)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_system_architecture')) {
                $table->string('paf_system_architecture', 40)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_cloud_provider')) {
                $table->string('paf_cloud_provider')->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_fuel_module_enabled')) {
                $table->boolean('paf_fuel_module_enabled')->default(false);
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_cnpj')) {
                $table->string('paf_developer_cnpj', 20)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_ie')) {
                $table->string('paf_developer_ie', 20)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_im')) {
                $table->string('paf_developer_im', 20)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_razao_social')) {
                $table->string('paf_developer_razao_social')->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_endereco')) {
                $table->string('paf_developer_endereco')->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_telefone')) {
                $table->string('paf_developer_telefone', 40)->nullable();
            }
            if (! Schema::hasColumn('fiscal_config', 'paf_developer_contato')) {
                $table->string('paf_developer_contato')->nullable();
            }
        });

        Schema::table('digital_certificate', function (Blueprint $table): void {
            if (! Schema::hasColumn('digital_certificate', 'pfx_storage_path')) {
                $table->string('pfx_storage_path')->nullable();
            }
            if (! Schema::hasColumn('digital_certificate', 'pfx_password_encrypted')) {
                $table->text('pfx_password_encrypted')->nullable();
            }
            if (! Schema::hasColumn('digital_certificate', 'pfx_uploaded_at')) {
                $table->timestamp('pfx_uploaded_at')->nullable();
            }
        });

        Schema::table('payment_methods', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_methods', 'paf_intermediator_cnpj')) {
                $table->string('paf_intermediator_cnpj', 20)->nullable();
            }
            if (! Schema::hasColumn('payment_methods', 'paf_intermediator_identifier')) {
                $table->string('paf_intermediator_identifier', 80)->nullable();
            }
        });

        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'paf_dav_id')) {
                $table->uuid('paf_dav_id')->nullable()->index();
            }
            if (! Schema::hasColumn('sales', 'paf_pre_sale_id')) {
                $table->uuid('paf_pre_sale_id')->nullable()->index();
            }
            if (! Schema::hasColumn('sales', 'paf_external_requisition_id')) {
                $table->uuid('paf_external_requisition_id')->nullable()->index();
            }
            if (! Schema::hasColumn('sales', 'fiscal_observation')) {
                $table->text('fiscal_observation')->nullable();
            }
        });

        Schema::table('sale_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('sale_payments', 'paf_document_type_code')) {
                $table->string('paf_document_type_code', 1)->default('1');
            }
        });

        Schema::create('paf_daily_payment_totals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('movement_date');
            $table->string('payment_method_name', 25);
            $table->string('document_type_code', 1);
            $table->string('customer_document', 14)->nullable();
            $table->string('non_tax_document_number', 10)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique([
                'movement_date',
                'payment_method_name',
                'document_type_code',
                'customer_document',
                'non_tax_document_number',
            ], 'paf_daily_payment_totals_unique');
        });

        Schema::create('paf_davs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('grupo_empresarial_id')->nullable()->index();
            $table->string('estabelecimento_id')->nullable()->index();
            $table->string('number', 13)->unique();
            $table->string('title', 30)->default('Pedido');
            $table->string('status', 30)->default('aberto');
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_document', 20)->nullable();
            $table->uuid('external_requisition_id')->nullable()->index();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->uuid('converted_sale_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('paf_dav_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dav_id');
            $table->unsignedInteger('item_number');
            $table->uuid('product_id')->nullable();
            $table->uuid('catalog_product_id')->nullable();
            $table->string('product_code', 14)->nullable();
            $table->string('description', 100);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 10)->default('UN');
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('tax_situation', 1)->default('T');
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->boolean('canceled')->default(false);
            $table->unsignedTinyInteger('quantity_decimals')->default(3);
            $table->unsignedTinyInteger('unit_price_decimals')->default(2);
            $table->timestamp('included_at')->nullable();
            $table->timestamps();

            $table->foreign('dav_id')->references('id')->on('paf_davs')->cascadeOnDelete();
            $table->unique(['dav_id', 'item_number']);
        });

        Schema::create('paf_dav_item_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dav_id');
            $table->uuid('dav_item_id')->nullable();
            $table->string('change_type', 1);
            $table->string('product_code', 14)->nullable();
            $table->string('description', 100);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 10)->default('UN');
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->string('tax_situation', 1)->default('T');
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->boolean('canceled')->default(false);
            $table->unsignedTinyInteger('quantity_decimals')->default(3);
            $table->unsignedTinyInteger('unit_price_decimals')->default(2);
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->foreign('dav_id')->references('id')->on('paf_davs')->cascadeOnDelete();
            $table->index(['dav_id', 'changed_at']);
        });

        Schema::create('paf_pre_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('status', 30)->default('aberta');
            $table->string('customer_name', 120)->nullable();
            $table->uuid('converted_sale_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('paf_pre_sale_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('pre_sale_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('catalog_product_id')->nullable();
            $table->string('product_code', 14)->nullable();
            $table->string('description', 100);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 10)->default('UN');
            $table->timestamp('included_at')->nullable();
            $table->timestamps();

            $table->foreign('pre_sale_id')->references('id')->on('paf_pre_sales')->cascadeOnDelete();
            $table->index(['pre_sale_id', 'created_at']);
        });

        Schema::create('paf_external_requisitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('cre', 9)->unique();
            $table->string('origin', 20)->default('OUTROS');
            $table->string('status', 1)->default('R');
            $table->string('external_order_id', 40)->nullable();
            $table->string('requester_cnpj', 20)->nullable();
            $table->uuid('dav_id')->nullable()->index();
            $table->uuid('pre_sale_id')->nullable()->index();
            $table->uuid('restaurant_ficha_id')->nullable()->index();
            $table->decimal('total', 14, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->uuid('attended_sale_id')->nullable()->index();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_conference_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ficha_id')->index();
            $table->string('number', 9)->unique();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_table_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_ficha_id')->nullable()->index();
            $table->uuid('to_ficha_id')->nullable()->index();
            $table->string('from_table_code', 30)->nullable();
            $table->string('to_table_code', 30)->nullable();
            $table->uuid('ticket_item_id')->nullable()->index();
            $table->string('product_code', 60)->nullable();
            $table->string('product_name', 160);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->timestamp('transferred_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_scale_customer_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier', 80)->unique();
            $table->uuid('ficha_id')->nullable()->index();
            $table->uuid('product_id')->nullable();
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_scale_customer_accounts');
        Schema::dropIfExists('restaurant_table_transfers');
        Schema::dropIfExists('restaurant_conference_reports');
        Schema::dropIfExists('paf_external_requisitions');
        Schema::dropIfExists('paf_pre_sale_items');
        Schema::dropIfExists('paf_pre_sales');
        Schema::dropIfExists('paf_dav_item_logs');
        Schema::dropIfExists('paf_dav_items');
        Schema::dropIfExists('paf_davs');
        Schema::dropIfExists('paf_daily_payment_totals');

        Schema::table('sale_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('sale_payments', 'paf_document_type_code')) {
                $table->dropColumn('paf_document_type_code');
            }
        });

        Schema::table('sales', function (Blueprint $table): void {
            foreach (['paf_dav_id', 'paf_pre_sale_id', 'paf_external_requisition_id', 'fiscal_observation'] as $column) {
                if (Schema::hasColumn('sales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payment_methods', function (Blueprint $table): void {
            foreach (['paf_intermediator_cnpj', 'paf_intermediator_identifier'] as $column) {
                if (Schema::hasColumn('payment_methods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('digital_certificate', function (Blueprint $table): void {
            foreach (['pfx_storage_path', 'pfx_password_encrypted', 'pfx_uploaded_at'] as $column) {
                if (Schema::hasColumn('digital_certificate', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('fiscal_config', function (Blueprint $table): void {
            foreach ([
                'paf_enabled',
                'paf_app_name',
                'paf_app_version',
                'paf_database_architecture',
                'paf_system_architecture',
                'paf_cloud_provider',
                'paf_fuel_module_enabled',
                'paf_developer_cnpj',
                'paf_developer_ie',
                'paf_developer_im',
                'paf_developer_razao_social',
                'paf_developer_endereco',
                'paf_developer_telefone',
                'paf_developer_contato',
            ] as $column) {
                if (Schema::hasColumn('fiscal_config', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
