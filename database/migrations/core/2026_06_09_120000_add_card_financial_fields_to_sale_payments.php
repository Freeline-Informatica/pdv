<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->uuid('payment_method_id')->nullable()->after('sale_id');
            $table->uuid('acquirer_terminal_id')->nullable()->after('descricao');
            $table->string('acquirer_terminal_type', 10)->nullable()->after('acquirer_terminal_id');
            $table->string('installment_type', 40)->nullable()->after('acquirer_terminal_type');
            $table->unsignedSmallInteger('installments')->default(1)->after('installment_type');
            $table->string('card_brand', 80)->nullable()->after('installments');
            $table->string('authorization_number', 80)->nullable()->after('card_brand');
            $table->string('nsu', 100)->nullable()->after('authorization_number');
            $table->json('tef_data')->nullable()->after('nsu');
            $table->decimal('acquirer_fee_rate', 10, 4)->default(0)->after('valor');
            $table->decimal('acquirer_fee_amount', 12, 2)->default(0)->after('acquirer_fee_rate');
            $table->decimal('expected_net_amount', 12, 2)->default(0)->after('acquirer_fee_amount');
            $table->unsignedSmallInteger('expected_receipt_days')->nullable()->after('expected_net_amount');

            $table->index('payment_method_id', 'sale_payments_method_idx');
            $table->index('acquirer_terminal_id', 'sale_payments_acquirer_terminal_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropIndex('sale_payments_method_idx');
            $table->dropIndex('sale_payments_acquirer_terminal_idx');
            $table->dropColumn([
                'payment_method_id',
                'acquirer_terminal_id',
                'acquirer_terminal_type',
                'installment_type',
                'installments',
                'card_brand',
                'authorization_number',
                'nsu',
                'tef_data',
                'acquirer_fee_rate',
                'acquirer_fee_amount',
                'expected_net_amount',
                'expected_receipt_days',
            ]);
        });
    }
};
