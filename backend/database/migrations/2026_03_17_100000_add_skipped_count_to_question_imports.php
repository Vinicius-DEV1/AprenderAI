<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            $table->integer('skipped_count')->default(0)->after('approved_count');
            $table->integer('updated_count')->default(0)->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            $table->dropColumn(['skipped_count', 'updated_count']);
        });
    }
};
