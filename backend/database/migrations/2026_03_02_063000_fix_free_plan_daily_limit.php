<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update Free plan to have 30 daily questions as promised in the UI
        DB::table('plans')
            ->where('slug', 'free')
            ->update(['daily_question_limit' => 30]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')
            ->where('slug', 'free')
            ->update(['daily_question_limit' => 0]);
    }
};
