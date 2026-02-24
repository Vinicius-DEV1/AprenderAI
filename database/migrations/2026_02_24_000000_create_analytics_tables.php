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

        Schema::create('analytics_hourlies', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('hour');
            $table->integer('active_users')->default(0);
            $table->integer('sessions')->default(0);
            $table->timestamps();

            $table->unique(['date', 'hour']);
        });

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

        Schema::create('analytics_devices', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('device_category');
            $table->integer('sessions')->default(0);
            $table->integer('users')->default(0);
            $table->timestamps();

            $table->unique(['date', 'device_category']);
        });

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

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('event_name');
            $table->integer('event_count')->default(0);
            $table->integer('users')->default(0);
            $table->timestamps();

            $table->unique(['date', 'event_name']);
        });

        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // drop, peak, growth, anomaly
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sources');
        Schema::dropIfExists('analytics_devices');
        Schema::dropIfExists('analytics_pages');
        Schema::dropIfExists('analytics_hourlies');
        Schema::dropIfExists('analytics_dailies');
    }
};
