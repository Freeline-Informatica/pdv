<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_terminals')) {
            return;
        }

        Schema::table('pos_terminals', function (Blueprint $table): void {
            if (! Schema::hasColumn('pos_terminals', 'printer_connection_mode')) {
                $table->string('printer_connection_mode', 20)
                    ->default('direct')
                    ->after('pdv_restaurant_mode');
            }

            if (! Schema::hasColumn('pos_terminals', 'printer_bridge_base_url')) {
                $table->string('printer_bridge_base_url', 255)
                    ->nullable()
                    ->after('printer_connection_mode');
            }

            if (! Schema::hasColumn('pos_terminals', 'printer_bridge_device_id')) {
                $table->string('printer_bridge_device_id', 80)
                    ->nullable()
                    ->after('printer_bridge_base_url');
            }

            if (! Schema::hasColumn('pos_terminals', 'scale_connection_mode')) {
                $table->string('scale_connection_mode', 20)
                    ->default('direct')
                    ->after('printer_bridge_device_id');
            }

            if (! Schema::hasColumn('pos_terminals', 'scale_bridge_base_url')) {
                $table->string('scale_bridge_base_url', 255)
                    ->nullable()
                    ->after('scale_connection_mode');
            }

            if (! Schema::hasColumn('pos_terminals', 'scale_bridge_device_id')) {
                $table->string('scale_bridge_device_id', 80)
                    ->nullable()
                    ->after('scale_bridge_base_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_terminals')) {
            return;
        }

        Schema::table('pos_terminals', function (Blueprint $table): void {
            if (Schema::hasColumn('pos_terminals', 'scale_bridge_device_id')) {
                $table->dropColumn('scale_bridge_device_id');
            }

            if (Schema::hasColumn('pos_terminals', 'scale_bridge_base_url')) {
                $table->dropColumn('scale_bridge_base_url');
            }

            if (Schema::hasColumn('pos_terminals', 'scale_connection_mode')) {
                $table->dropColumn('scale_connection_mode');
            }

            if (Schema::hasColumn('pos_terminals', 'printer_bridge_device_id')) {
                $table->dropColumn('printer_bridge_device_id');
            }

            if (Schema::hasColumn('pos_terminals', 'printer_bridge_base_url')) {
                $table->dropColumn('printer_bridge_base_url');
            }

            if (Schema::hasColumn('pos_terminals', 'printer_connection_mode')) {
                $table->dropColumn('printer_connection_mode');
            }
        });
    }
};
