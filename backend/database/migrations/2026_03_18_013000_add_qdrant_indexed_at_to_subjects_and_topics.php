<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->timestamp('qdrant_indexed_at')->nullable()->after('type');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->timestamp('qdrant_indexed_at')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('qdrant_indexed_at');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('qdrant_indexed_at');
        });
    }
};
