<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('question_imports', 'updated_count')) {
            Schema::table('question_imports', function (Blueprint $table) {
                // Se skipped_count não existir por algum motivo, adicionamos após approved_count
                $afterColumn = Schema::hasColumn('question_imports', 'skipped_count') ? 'skipped_count' : 'approved_count';
                $table->integer('updated_count')->default(0)->after($afterColumn);
            });
        }
    }

    public function down(): void
    {
        Schema::table('question_imports', function (Blueprint $table) {
            $table->dropColumn('updated_count');
        });
    }
};
