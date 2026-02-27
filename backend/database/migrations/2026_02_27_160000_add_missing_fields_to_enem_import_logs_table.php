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
        Schema::table('enem_import_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('enem_import_logs', 'processed')) {
                $table->integer('processed')->default(0)->after('error_count');
            }
            if (!Schema::hasColumn('enem_import_logs', 'total')) {
                $table->integer('total')->default(0)->after('processed');
            }
            if (!Schema::hasColumn('enem_import_logs', 'progress')) {
                $table->integer('progress')->default(0)->after('total');
            }
            if (!Schema::hasColumn('enem_import_logs', 'finished')) {
                $table->boolean('finished')->default(false)->after('progress');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enem_import_logs', function (Blueprint $table) {
            $table->dropColumn(['processed', 'total', 'progress', 'finished']);
        });
    }
};
