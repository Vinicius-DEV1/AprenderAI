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
        // Modify status to string/varchar to allow new statuses without dbal issues
        // We use raw statement because strict mode or lack of dbal prevents ->change()
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE essays MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");

        Schema::table('essays', function (Blueprint $table) {
            // New columns
            if (!Schema::hasColumn('essays', 'type')) {
                $table->enum('type', ['enem', 'concurso'])->default('enem')->after('user_id');
            }
            if (!Schema::hasColumn('essays', 'time_limit')) {
                $table->integer('time_limit')->nullable()->after('type');
            }
            if (!Schema::hasColumn('essays', 'topic_description')) {
                $table->text('topic_description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('essays', 'topic_regen_count')) {
                $table->tinyInteger('topic_regen_count')->default(0)->after('topic_description');
            }
            if (!Schema::hasColumn('essays', 'topic_hash')) {
                $table->string('topic_hash')->nullable()->after('topic_regen_count');
            }
            if (!Schema::hasColumn('essays', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('created_at');
            }
            if (!Schema::hasColumn('essays', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('essays', 'evaluated_at')) {
                $table->timestamp('evaluated_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('essays', 'feedback_json')) {
                $table->json('feedback_json')->nullable()->after('score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'time_limit',
                'topic_description',
                'topic_regen_count',
                'topic_hash',
                'started_at',
                'submitted_at',
                'evaluated_at',
                'feedback_json'
            ]);
        });

        // Revert status to enum if possible, or leave as string (safer to leave as string to avoid data loss on rollback if statuses greatly differ)
        // \Illuminate\Support\Facades\DB::statement("ALTER TABLE essays MODIFY COLUMN status ENUM('pending', 'correcting', 'corrected') DEFAULT 'pending'");
    }
};
