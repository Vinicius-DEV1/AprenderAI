<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_triage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('triage_type'); // 'ai_batch' | 'manual'
            $table->string('status');      // 'approved' | 'manual_review'
            $table->json('issues_detected')->nullable();
            $table->unsignedSmallInteger('quality_score')->nullable();
            $table->json('changes_made')->nullable();
            $table->string('processed_by'); // 'system' | admin user id as string
            $table->timestamp('created_at')->useCurrent();

            $table->index(['question_id', 'created_at']);
            $table->index('triage_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_triage_logs');
    }
};
