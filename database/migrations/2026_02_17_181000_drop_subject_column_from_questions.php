<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['type', 'subject', 'theme']); // Laravel naming convention or array
            $table->dropColumn('subject');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('subject')->nullable(); // Re-add as nullable string for rollback
        });
    }
};
