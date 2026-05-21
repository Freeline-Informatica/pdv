<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('action_key', 120);
            $table->string('action_label', 160);
            $table->string('entity', 120);
            $table->string('entity_id', 80)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_name')->nullable();
            $table->string('operator_code', 120)->nullable();
            $table->string('operator_role', 40)->nullable();
            $table->text('details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('action_key');
            $table->index('entity');
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
