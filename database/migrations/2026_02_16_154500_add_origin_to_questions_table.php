<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Adding origin column after source. 
            // It allows NULL because not all questions might have a known origin or are AI generated.
            $table->string('origin')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
