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
        Schema::table('enem_import_logs', function (Blueprint $table) {
            $table->json('ignored_details')->nullable()->after('errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enem_import_logs', function (Blueprint $table) {
            $table->dropColumn('ignored_details');
        });
    }
};
