<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->string('provider'); // 'openai', 'gemini', 'grok'
                $table->text('key');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_primary')->default(false);
                $table->timestamp('last_used_at')->nullable();
                $table->integer('requests_count')->default(0);
                $table->string('preferred_model')->nullable();
                $table->boolean('is_valid')->default(false);
                $table->string('status')->default('online');
                $table->timestamp('last_health_check_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('api_logs')) {
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
        }

        if (!Schema::hasTable('enem_import_logs')) {
            Schema::create('enem_import_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('year');
                $table->integer('inserted_count')->default(0);
                $table->integer('ignored_count')->default(0);
                $table->integer('error_count')->default(0);
                $table->text('errors')->nullable();
                $table->string('status')->default('processing'); // processing, completed, failed
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_processing_batches')) {
            Schema::create('ai_processing_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->string('model')->nullable();
                $table->string('type');
                $table->integer('total_count');
                $table->integer('processed_count')->default(0);
                $table->integer('error_count')->default(0);
                $table->string('status')->default('processing'); // processing, completed, failed, cancelled
                $table->text('errors_log')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_logs')) {
            Schema::create('user_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('action');
                $table->text('description')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_search_requests')) {
            Schema::create('ai_search_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('prompt');
                $table->text('filters')->nullable();
                $table->string('status')->default('pending');
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_request_logs')) {
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
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_search_requests');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('ai_processing_batches');
        Schema::dropIfExists('enem_import_logs');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_keys');
    }
};
