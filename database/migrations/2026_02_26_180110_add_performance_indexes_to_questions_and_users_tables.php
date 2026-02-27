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
            $table->index('type');
            $table->index('difficulty');
            $table->index('year');
            $table->index('organization');
            $table->index('institution');
            $table->index('role');
            $table->index('review_status');
            $table->index('source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('is_banned');
        });

        if (Schema::hasTable('question_subject')) {
            Schema::table('question_subject', function (Blueprint $table) {
                $table->index('subject_id');
            });
        }

        if (Schema::hasTable('question_topic')) {
            Schema::table('question_topic', function (Blueprint $table) {
                $table->index('topic_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('question_topic')) {
            Schema::table('question_topic', function (Blueprint $table) {
                $table->dropIndex(['topic_id']);
            });
        }

        if (Schema::hasTable('question_subject')) {
            Schema::table('question_subject', function (Blueprint $table) {
                $table->dropIndex(['subject_id']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_banned']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['difficulty']);
            $table->dropIndex(['year']);
            $table->dropIndex(['organization']);
            $table->dropIndex(['institution']);
            $table->dropIndex(['role']);
            $table->dropIndex(['review_status']);
            $table->dropIndex(['source']);
        });
    }
};
