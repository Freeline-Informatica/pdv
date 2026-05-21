<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->uuid('catalog_product_id')->nullable()->after('product_id');
            $table->foreign('catalog_product_id')->references('id')->on('produto')->nullOnDelete();
            $table->index(['catalog_product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['catalog_product_id']);
            $table->dropIndex(['catalog_product_id', 'created_at']);
            $table->dropColumn('catalog_product_id');
        });
    }
};
