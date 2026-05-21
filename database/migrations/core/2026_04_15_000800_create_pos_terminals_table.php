<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nome', 80);
            $table->string('identificador', 30)->unique();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
    }
};
