<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('writing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->integer('min_chars')->default(0);
            $table->integer('max_chars')->default(5000);
            $table->integer('max_lines')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_rules');
    }
};
