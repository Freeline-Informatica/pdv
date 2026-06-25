<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('acquirer_terminal_rates')
            || ! Schema::hasColumn('acquirer_terminal_rates', 'parc_maximo')
        ) {
            return;
        }

        if (Schema::hasColumn('acquirer_terminal_rates', 'parc_final')) {
            DB::table('acquirer_terminal_rates')
                ->whereNull('parc_final')
                ->whereNotNull('parc_maximo')
                ->update(['parc_final' => DB::raw('parc_maximo')]);
        }

        Schema::table('acquirer_terminal_rates', function (Blueprint $table): void {
            $table->dropColumn('parc_maximo');
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('acquirer_terminal_rates')
            || Schema::hasColumn('acquirer_terminal_rates', 'parc_maximo')
        ) {
            return;
        }

        Schema::table('acquirer_terminal_rates', function (Blueprint $table): void {
            $table->integer('parc_maximo')->nullable();
        });

        if (Schema::hasColumn('acquirer_terminal_rates', 'parc_final')) {
            DB::table('acquirer_terminal_rates')
                ->whereNull('parc_maximo')
                ->whereNotNull('parc_final')
                ->update(['parc_maximo' => DB::raw('parc_final')]);
        }
    }
};
