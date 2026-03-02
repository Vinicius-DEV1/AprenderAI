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
            $table->integer('pdf_page')->nullable()->after('discursive_answer');
            $table->string('origin')->nullable()->after('pdf_page');
            $table->string('source_url')->nullable()->after('origin');
            $table->string('extracted_at')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['pdf_page', 'origin', 'source_url', 'extracted_at']);
        });
    }
};
