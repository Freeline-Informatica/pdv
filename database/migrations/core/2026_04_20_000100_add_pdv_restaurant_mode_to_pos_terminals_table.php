<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->string('pdv_restaurant_mode', 30)
                ->nullable()
                ->after('pdv_layout_mode');
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->dropColumn('pdv_restaurant_mode');
        });
    }
};
