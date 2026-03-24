<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('frontend_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type')->nullable(); // info, warning, error
            $table->text('message');
            $table->text('url')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('user_action')->nullable();
            $table->json('additional_data')->nullable(); // extra payload
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frontend_errors');
    }
};
