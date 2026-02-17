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
            $table->string('organization')->nullable()->after('year')->index(); // Banca (ex: FGV)
            $table->string('institution')->nullable()->after('organization')->index(); // Órgão (ex: TJ-SP)
            $table->string('role')->nullable()->after('institution')->index(); // Cargo (ex: Juiz)

            // Alterar subject para string para permitir mais flexibilidade na importação
            // Como SQLite/MySQL lidam diferente com alteração de enum, vamos usar DB::statement se necessário ou change()
            // Mas change() em enum pode ser chato. Vamos tentar change() primeiro.
            $table->string('subject')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['organization', 'institution', 'role']);
        // Reverter subject é arriscado se tiver valores novos, mas vamos tentar voltar para enum
        // $table->enum('subject', ['matemática', 'português'])->change(); 
        });
    }
};
