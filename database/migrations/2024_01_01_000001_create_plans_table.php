<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Gratuito, Básico, Plus
            $table->string('slug')->unique(); // free, basic, plus
            $table->decimal('price', 8, 2)->default(0);
            $table->string('interval')->default('month'); // month, year
            $table->integer('simulations_limit')->default(0); // 0 = ilimitado
            $table->integer('essays_limit')->default(0);
            $table->json('features')->nullable(); // Funcionalidades extras
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
