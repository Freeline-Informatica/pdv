<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->string('settings_access_hash', 64)->nullable();
            $table->timestamp('settings_access_expires_at')->nullable();
            $table->foreignId('settings_access_granted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index('settings_access_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settings_access_granted_by_user_id');
            $table->dropIndex(['settings_access_expires_at']);
            $table->dropColumn(['settings_access_hash', 'settings_access_expires_at']);
        });
    }
};
