<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 - Core Relational Tables
 * All tables that depend on users, plans, questions, simulations, etc.
 * Consolidated from multiple original migrations.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Billing / Subscriptions ──────────────────────────────────────────
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('gateway')->nullable();
            $table->string('gateway_id')->nullable();
            $table->string('billing_type')->nullable();
            $table->decimal('amount', 10, 2)->nullable()->comment('Valor cobrado na assinatura');
            $table->text('pix_payload')->nullable();
            $table->longText('pix_image')->nullable();
            $table->timestamp('pix_expires_at')->nullable()->comment('Quando o QR Code Pix expira (30min após criação)');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->json('limits');
            $table->boolean('has_used_cumulative_bonus')->default(false);
            $table->timestamps();

            $table->index(['subscription_id', 'end_date']);
        });

        Schema::create('usage_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_cycle_id')->constrained()->onDelete('cascade');
            $table->string('feature_name', 50);
            $table->integer('amount')->default(1);
            $table->string('type', 50)->default('consumption');
            $table->timestamps();

            $table->index(['subscription_cycle_id', 'feature_name']);
        });

        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway')->default('asaas');
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('event');
            $table->string('status');
            $table->text('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // ─── Now add triggered_by FK to backup_jobs ────────────────────────────
        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // ─── Now add updated_by FK to api_pricing ─────────────────────────────
        Schema::table('api_pricing', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('api_pricing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_pricing_id')->constrained('api_pricing')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_input_price_per_1m', 20, 8)->default(0);
            $table->decimal('old_output_price_per_1m', 20, 8)->default(0);
            $table->decimal('new_input_price_per_1m', 20, 8)->default(0);
            $table->decimal('new_output_price_per_1m', 20, 8)->default(0);
            $table->timestamps();
        });

        // ─── API Keys ─────────────────────────────────────────────────────────
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->nullable()->constrained('api_key_vaults')->nullOnDelete();
            $table->string('provider')->nullable(); // nullable because now fetched from vault
            $table->text('key')->nullable();         // nullable because now fetched from vault
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('requests_count')->default(0);
            $table->string('preferred_model')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_valid')->default(false);
            $table->string('status')->default('online');
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_key_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('capability');
            $table->integer('priority')->default(1);
            $table->timestamps();

            $table->unique(['api_key_id', 'capability']);
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('provider');
            $table->string('type');
            $table->integer('status_code')->nullable();
            $table->text('message')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        // ─── Questions (core entity) ──────────────────────────────────────────
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_questao')->default('Objetiva');
            $table->string('number')->nullable();
            $table->string('type'); // 'enem', 'concurso'
            $table->string('knowledge_area')->nullable()->index()->comment('Grande área do conhecimento');
            $table->string('format')->default('multiple_choice');
            $table->string('arquivo_origem')->nullable();
            $table->string('difficulty')->default('medium');
            $table->text('difficulty_reasoning')->nullable();
            $table->integer('year')->nullable();
            $table->text('statement');
            $table->text('explanation')->nullable();
            $table->json('discursive_answer')->nullable();
            $table->integer('pdf_page')->nullable();
            $table->string('origin')->nullable();
            $table->string('source_url')->nullable();
            $table->string('extracted_at')->nullable();
            $table->string('source')->default('manual');
            $table->string('organization')->nullable();
            $table->string('institution')->nullable();
            $table->string('role')->nullable();
            $table->string('theme')->nullable();
            $table->string('topic')->nullable();
            $table->string('external_id');
            $table->string('review_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Performance indexes
            $table->index('type');
            $table->index('difficulty');
            $table->index('year');
            $table->index('organization');
            $table->index('institution');
            $table->index('role');
            $table->index('review_status');
            $table->index('source');
        });

        Schema::create('question_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // 'A', 'B', 'C', 'D', 'E'
            $table->text('content')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('question_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('question_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_name');
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->integer('total_questions')->default(0);
            $table->integer('pending_count')->default(0);
            $table->integer('approved_count')->default(0);
            $table->integer('processed_questions')->default(0);
            $table->string('status')->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('question_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('question_imports')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        // ─── Pivot: Question ↔ Subject / Topic ────────────────────────────────
        Schema::create('question_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'subject_id']);
            $table->index('subject_id');
        });

        Schema::create('question_topic', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();

            $table->primary(['question_id', 'topic_id']);
            $table->index('topic_id');
        });

        // ─── Question Engagement ─────────────────────────────────────────────
        Schema::create('question_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action'); // 'viewed' or 'answered'
            $table->boolean('is_correct')->nullable();
            $table->integer('time_spent_seconds')->nullable();
            $table->string('source')->nullable(); // 'banco', 'simulado', 'treino'
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action']);
            $table->index('created_at');
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
        });

        Schema::create('question_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, resolved
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('discursive_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('submitted_answer');
            $table->decimal('score', 5, 2)->nullable();
            $table->json('ai_feedback')->nullable();
            $table->timestamps();
        });

        // ─── Notebooks & Favorites ────────────────────────────────────────────
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('notebook_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['notebook_id', 'question_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
        });

        // ─── Simulations ─────────────────────────────────────────────────────
        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'enem', 'concurso'
            $table->text('configuration');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('time_elapsed')->default(0);
            $table->string('status')->default('pending');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('scores_by_subject')->nullable();
            $table->text('analysis_by_theme')->nullable();
            $table->timestamps();
        });

        Schema::create('simulation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('user_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('time_spent')->default(0);
            $table->boolean('marked_for_review')->default(false);
            $table->timestamps();
        });

        Schema::create('simulation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('simulation_presets')->onDelete('cascade');
            $table->string('category');
            $table->json('configuration');
            $table->timestamps();
        });

        Schema::create('simulation_engine_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('simulation_models')->onDelete('cascade');
            $table->integer('total_questoes')->default(90);
            $table->integer('tempo_minutos')->default(270);
            $table->decimal('percentual_ia', 5, 2)->default(10.00);
            $table->integer('nao_repetir_ultimos_simulados')->default(10);
            $table->enum('difficulty_mode', ['balanceado', 'progressivo', 'aleatorio'])->default('balanceado');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('simulation_discipline_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('simulation_engine_rules')->onDelete('cascade');
            $table->string('disciplina');
            $table->decimal('percentual', 5, 2);
            $table->string('dificuldade')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        // ─── Essays ───────────────────────────────────────────────────────────
        Schema::create('essays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('enem'); // enem, concurso
            $table->string('input_type')->default('text');
            $table->string('title');
            $table->longText('content');
            $table->string('image_path')->nullable();
            $table->string('ocr_status')->nullable();
            $table->text('ocr_error')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('status')->default('pending');
            $table->integer('score')->nullable();
            $table->boolean('off_topic')->default(false);
            $table->text('off_topic_reason')->nullable();
            $table->boolean('final_score_locked')->default(false);
            $table->text('competencies')->nullable();
            $table->text('feedback')->nullable();
            $table->text('ai_suggestions')->nullable();
            $table->text('example_essay')->nullable();
            $table->integer('time_limit')->nullable();
            $table->text('topic_description')->nullable();
            $table->integer('topic_regen_count')->default(0);
            $table->string('topic_hash')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->text('feedback_json')->nullable();
            $table->timestamps();
        });

        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->morphs('correctable'); // correctable_id + correctable_type
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->longText('correction_data')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
        });

        // ─── AI Processing ────────────────────────────────────────────────────
        Schema::create('ai_processing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id');
            $table->string('model')->nullable();
            $table->string('type');
            $table->integer('total_count');
            $table->integer('processed_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status')->default('processing');
            $table->text('errors_log')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_batch_items', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('snapshot_before')->nullable();
            $table->json('snapshot_after')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
        });

        Schema::create('ai_search_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->text('filters')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('api_key_name')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->text('prompt_text')->nullable();
            $table->text('response_text')->nullable();
            $table->integer('tokens_used_input')->default(0);
            $table->integer('tokens_used_output')->default(0);
            $table->integer('tokens_used_total')->default(0);
            $table->float('execution_time')->nullable();
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->timestamps();
        });

        // ─── Logs ─────────────────────────────────────────────────────────────
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        // ─── User Stats ───────────────────────────────────────────────────────
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('total_simulations')->default(0);
            $table->integer('total_essays')->default(0);
            $table->decimal('average_math_score', 8, 2)->default(0);
            $table->decimal('average_portuguese_score', 8, 2)->default(0);
            $table->decimal('average_overall_score', 8, 2)->default(0);
            $table->integer('total_time_studied')->default(0);
            $table->text('weak_themes')->nullable();
            $table->text('strong_themes')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('user_topic_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('topic');
            $table->integer('attempts')->default(0);
            $table->integer('correct')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('selected_answer');
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('user_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedInteger('total_answered')->default(0);
            $table->unsignedInteger('total_correct')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('question_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('message');
            $table->timestamps();
        });

        // ─── Study Plans ──────────────────────────────────────────────────────
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam_type');
            $table->string('exam_name')->nullable();
            $table->date('exam_date')->nullable();
            $table->integer('hours_per_day');
            $table->text('plan_json')->nullable();
            $table->text('stats_snapshot')->nullable();
            $table->string('status')->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('next_generate_at')->nullable();
            $table->timestamp('next_update_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('question_interactions');
        Schema::dropIfExists('user_daily_stats');
        Schema::dropIfExists('user_question_answers');
        Schema::dropIfExists('user_topic_stats');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_search_requests');
        Schema::dropIfExists('ai_batch_items');
        Schema::dropIfExists('ai_processing_batches');
        Schema::dropIfExists('corrections');
        Schema::dropIfExists('essays');
        Schema::dropIfExists('simulation_discipline_distributions');
        Schema::dropIfExists('simulation_engine_rules');
        Schema::dropIfExists('simulation_rules');
        Schema::dropIfExists('simulation_answers');
        Schema::dropIfExists('simulations');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('notebook_questions');
        Schema::dropIfExists('notebooks');
        Schema::dropIfExists('discursive_responses');
        Schema::dropIfExists('question_reports');
        Schema::dropIfExists('question_notes');
        Schema::dropIfExists('question_event_logs');
        Schema::dropIfExists('question_topic');
        Schema::dropIfExists('question_subject');
        Schema::dropIfExists('question_import_items');
        Schema::dropIfExists('question_imports');
        Schema::dropIfExists('question_images');
        Schema::dropIfExists('question_alternatives');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_key_capabilities');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('api_pricing_logs');

        Schema::table('api_pricing', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });

        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->dropForeign(['triggered_by']);
            $table->dropColumn('triggered_by');
        });

        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('coupon_user');
        Schema::dropIfExists('usage_ledgers');
        Schema::dropIfExists('subscription_cycles');
        Schema::dropIfExists('subscriptions');
    }
};
