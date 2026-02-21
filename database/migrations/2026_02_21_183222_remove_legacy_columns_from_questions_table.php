<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // A coluna 'theme' foi preservada para uso futuro, pois contém o Eixo Temático essencial retornado pela API ENEM Dev.
            $table->dropColumn(['topic', 'origin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('topic')->nullable();
            $table->string('origin')->nullable();
        });
    }
};
