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
        Schema::table('question_imports', function (Blueprint $table) {
            $table->integer('processed_questions')->default(0)->after('approved_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            $table->dropColumn('processed_questions');
        });
    }
};
