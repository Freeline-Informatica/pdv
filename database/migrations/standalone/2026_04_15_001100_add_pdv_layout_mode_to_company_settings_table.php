<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('pdv_layout_mode', 20)
                ->default('varejo')
                ->after('uf');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn('pdv_layout_mode');
        });
    }
};
