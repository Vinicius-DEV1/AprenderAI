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
        Schema::table('user_question_answers', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['user_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_question_answers', function (Blueprint $table) {
            // Restore the unique constraint
            $table->unique(['user_id', 'question_id']);
        });
    }
};
