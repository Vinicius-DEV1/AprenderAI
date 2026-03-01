<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->boolean('off_topic')->default(false)->after('score');
            $table->text('off_topic_reason')->nullable()->after('off_topic');
            $table->boolean('final_score_locked')->default(false)->after('off_topic_reason');
        });
    }

    public function down(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->dropColumn(['off_topic', 'off_topic_reason', 'final_score_locked']);
        });
    }
};
