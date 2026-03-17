<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            if (!Schema::hasColumn('question_imports', 'skipped_count')) {
                $table->integer('skipped_count')->default(0)->after('approved_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            $table->dropColumn('skipped_count');
        });
    }
};
