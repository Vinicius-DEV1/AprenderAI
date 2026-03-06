<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 - Foundation Tables (no foreign key dependencies on users/questions)
 * Consolidated from multiple original migrations.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Billing ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->decimal('price', 8, 2)->default(0);
                $table->string('interval')->default('month');
                $table->integer('simulations_limit')->default(0);
                $table->integer('essays_limit')->default(0);
                $table->integer('daily_question_limit')->default(0);
                $table->text('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('max_ai_questions')->default(10);
                $table->decimal('monthly_price', 8, 2)->nullable();
                $table->decimal('annual_price', 8, 2)->nullable();
                $table->integer('discount_percentage')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('type'); // 'percent', 'fixed'
                $table->decimal('value', 8, 2);
                $table->integer('max_uses')->nullable();
                $table->integer('used_count')->default(0);
                $table->dateTime('start_date')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ─── Subjects / Topics ────────────────────────────────────────────────
        if (!Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('type')->nullable(); // 'enem', 'concurso', 'shared'
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->timestamps();
            });
        }

        // ─── System / Config ──────────────────────────────────────────────────
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('configurations')) {
            Schema::create('configurations', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('server_metrics')) {
            Schema::create('server_metrics', function (Blueprint $table) {
                $table->id();
                $table->float('cpu_usage');
                $table->float('ram_usage');
                $table->integer('net_rx_speed')->default(0);
                $table->integer('net_tx_speed')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('backup_jobs')) {
            Schema::create('backup_jobs', function (Blueprint $table) {
                $table->id();
                $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
                $table->string('s3_bucket')->nullable();
                $table->string('s3_key')->nullable();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->string('error_message', 1000)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                // triggered_by FK added in M3 after users table
            });
        }

        // ─── API / Pricing ────────────────────────────────────────────────────
        if (!Schema::hasTable('api_key_vaults')) {
            Schema::create('api_key_vaults', function (Blueprint $table) {
                $table->id();
                $table->string('nickname')->unique();
                $table->string('provider'); // openai, gemini, grok
                $table->text('key'); // encrypted
                $table->boolean('is_valid')->default(false);
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('api_pricing')) {
            Schema::create('api_pricing', function (Blueprint $table) {
                $table->id();
                $table->string('api_name');
                $table->string('model_key')->unique();
                $table->decimal('input_price_per_1m', 20, 8)->default(0);
                $table->decimal('output_price_per_1m', 20, 8)->default(0);
                // updated_by FK added in M3 after users table
                $table->timestamps();
            });
        }

        // ─── Simulation Presets & Models ──────────────────────────────────────
        if (!Schema::hasTable('simulation_presets')) {
            Schema::create('simulation_presets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type'); // enem, banca
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simulation_models')) {
            Schema::create('simulation_models', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('nome');
                $table->string('tipo')->default('enem'); // enem | concurso
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        // ─── Writing Rules ────────────────────────────────────────────────────
        if (!Schema::hasTable('writing_rules')) {
            Schema::create('writing_rules', function (Blueprint $table) {
                $table->id();
                $table->string('type')->unique();
                $table->integer('min_chars')->default(0);
                $table->integer('max_chars')->default(5000);
                $table->integer('max_lines')->default(30);
                $table->timestamps();
            });
        }

        // ─── System Prompts ───────────────────────────────────────────────────
        if (!Schema::hasTable('system_prompts')) {
            Schema::create('system_prompts', function (Blueprint $table) {
                $table->id();
                $table->string('slug');
                $table->string('title');
                $table->text('description')->nullable();
                $table->longText('content');
                $table->text('variables')->nullable();
                $table->timestamps();
            });
        }

        // ─── Analytics ────────────────────────────────────────────────────────
        if (!Schema::hasTable('analytics_dailies')) {
            Schema::create('analytics_dailies', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->integer('active_users')->default(0);
                $table->integer('sessions')->default(0);
                $table->integer('new_users')->default(0);
                $table->float('bounce_rate')->default(0);
                $table->float('avg_session_duration')->default(0);
                $table->float('screen_page_views_per_session')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('analytics_hourlies')) {
            Schema::create('analytics_hourlies', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->integer('hour');
                $table->integer('active_users')->default(0);
                $table->integer('sessions')->default(0);
                $table->timestamps();
                $table->unique(['date', 'hour']);
            });
        }

        if (!Schema::hasTable('analytics_pages')) {
            Schema::create('analytics_pages', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('page_path');
                $table->string('page_title')->nullable();
                $table->integer('views')->default(0);
                $table->float('avg_time_on_page')->default(0);
                $table->float('exit_rate')->default(0);
                $table->timestamps();
                $table->unique(['date', 'page_path']);
            });
        }

        if (!Schema::hasTable('analytics_devices')) {
            Schema::create('analytics_devices', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('device_category');
                $table->integer('sessions')->default(0);
                $table->integer('users')->default(0);
                $table->timestamps();
                $table->unique(['date', 'device_category']);
            });
        }

        if (!Schema::hasTable('analytics_sources')) {
            Schema::create('analytics_sources', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('source_medium');
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->integer('sessions')->default(0);
                $table->integer('users')->default(0);
                $table->timestamps();
                $table->unique(['date', 'source_medium', 'country', 'city'], 'analytics_sources_unique');
            });
        }

        if (!Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('event_name');
                $table->integer('event_count')->default(0);
                $table->integer('users')->default(0);
                $table->timestamps();
                $table->unique(['date', 'event_name']);
            });
        }

        if (!Schema::hasTable('analytics_alerts')) {
            Schema::create('analytics_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // drop, peak, growth, anomaly
                $table->text('message');
                $table->json('details')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Concursos ────────────────────────────────────────────────────────
        if (!Schema::hasTable('concursos')) {
            Schema::create('concursos', function (Blueprint $table) {
                $table->id();
                $table->string('uf');
                $table->string('orgao');
                $table->string('cargo')->nullable();
                $table->string('situacao');
                $table->decimal('salario_maximo', 10, 2)->nullable();
                $table->integer('vagas')->nullable();
                $table->string('link_oficial')->nullable();
                $table->string('fonte');
                $table->timestamp('inscricoes_inicio')->nullable();
                $table->timestamp('inscricoes_fim')->nullable();
                $table->timestamp('ultimo_status_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── AI Search Cache (no FK to users) ────────────────────────────────
        if (!Schema::hasTable('ai_search_cache')) {
            Schema::create('ai_search_cache', function (Blueprint $table) {
                $table->id();
                $table->string('prompt_hash')->unique();
                $table->text('prompt_text');
                $table->json('embedding');
                $table->json('filters_result');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── ENEM Import Logs (no FK) ─────────────────────────────────────────
        if (!Schema::hasTable('enem_import_logs')) {
            Schema::create('enem_import_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('year');
                $table->integer('inserted_count')->default(0);
                $table->integer('ignored_count')->default(0);
                $table->integer('error_count')->default(0);
                $table->integer('processed')->default(0);
                $table->integer('total')->default(0);
                $table->integer('progress')->default(0);
                $table->boolean('finished')->default(false);
                $table->text('errors')->nullable();
                $table->json('ignored_details')->nullable();
                $table->string('status')->default('processing'); // processing, completed, failed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concursos');
        Schema::dropIfExists('enem_import_logs');
        Schema::dropIfExists('ai_search_cache');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sources');
        Schema::dropIfExists('analytics_devices');
        Schema::dropIfExists('analytics_pages');
        Schema::dropIfExists('analytics_hourlies');
        Schema::dropIfExists('analytics_dailies');
        Schema::dropIfExists('system_prompts');
        Schema::dropIfExists('writing_rules');
        Schema::dropIfExists('simulation_models');
        Schema::dropIfExists('simulation_presets');
        Schema::dropIfExists('api_pricing');
        Schema::dropIfExists('api_key_vaults');
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('server_metrics');
        Schema::dropIfExists('configurations');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('plans');
    }
};
