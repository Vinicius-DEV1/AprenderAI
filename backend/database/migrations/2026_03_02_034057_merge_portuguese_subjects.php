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
        // ID 1: LÍNGUA PORTUGUESA
        // ID 11: PORTUGUÊS

        $targetId = 1;
        $sourceId = 11;

        // 1. Move all relations from source to target
        // We use insert ignore or similar to avoid duplicates if a question has both subjects
        $relations = DB::table('question_subject')->where('subject_id', $sourceId)->get();

        foreach ($relations as $rel) {
            $exists = DB::table('question_subject')
                ->where('question_id', $rel->question_id)
                ->where('subject_id', $targetId)
                ->exists();

            if (!$exists) {
                DB::table('question_subject')->insert([
                    'question_id' => $rel->question_id,
                    'subject_id' => $targetId,
                    'created_at' => $rel->created_at,
                    'updated_at' => $rel->updated_at,
                ]);
            }
        }

        // 2. Delete source relations
        DB::table('question_subject')->where('subject_id', $sourceId)->delete();

        // 3. Delete source subject
        DB::table('subjects')->where('id', $sourceId)->delete();

        // 4. Update any simulations configuration that might reference 'PORTUGUÊS'
        // (Optional but good for data integrity)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy reverse for data consolidation without a backup table
    }
};
