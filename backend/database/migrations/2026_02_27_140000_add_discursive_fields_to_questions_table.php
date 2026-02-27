<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('tipo_questao')->default('Objetiva')->after('id');
            $table->string('number')->nullable()->after('tipo_questao');
            $table->string('arquivo_origem')->nullable()->after('format');
            $table->json('discursive_answer')->nullable()->after('explanation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['tipo_questao', 'number', 'arquivo_origem', 'discursive_answer']);
        });
    }
};
