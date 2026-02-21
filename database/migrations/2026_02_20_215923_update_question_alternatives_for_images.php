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
        Schema::table('question_alternatives', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->string('image_path')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_alternatives', function (Blueprint $table) {
            $table->dropColumn('image_path');
            $table->text('content')->nullable(false)->change();
        });
    }
};
