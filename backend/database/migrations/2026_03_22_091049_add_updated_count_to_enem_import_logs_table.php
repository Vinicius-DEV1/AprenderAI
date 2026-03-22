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
            $table->integer('updated_count')->default(0)->after('inserted_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enem_import_logs', function (Blueprint $table) {
            $table->dropColumn('updated_count');
        });
    }
};
