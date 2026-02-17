<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateSubjectsCommand extends Command
{
    protected $signature = 'utils:migrate-subjects {--force : Force execution without confirmation}';
    protected $description = 'Migrates data from questions.subject column to question_subject pivot table with verification.';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('This will migrate data to the new pivot table. Do you wish to continue?')) {
            return;
        }

        $this->info("Starting migration...");

        $questions = Question::whereNotNull('subject')->get();
        $totalQuestions = $questions->count();
        $linksCreated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($totalQuestions);
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($questions as $question) {
                $subjectName = $question->subject;

                // Skip if empty
                if (empty($subjectName)) {
                    $bar->advance();
                    continue;
                }

                // Find or Create Subject
                $subject = Subject::firstOrCreate(
                ['name' => $subjectName],
                [
                    'slug' => Str::slug($subjectName),
                    'type' => $question->type // fallback to question type for initial migration
                ]
                );

                // Attach if not already attached
                if (!$question->subjects()->where('subject_id', $subject->id)->exists()) {
                    $question->subjects()->attach($subject->id);
                    $linksCreated++;
                }

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();

            $this->info("Migration completed.");
            $this->info("Total de questões processadas: {$totalQuestions} | Total de vínculos criados: {$linksCreated}");

            // Verification Logic
            if ($totalQuestions !== $linksCreated) {
                // Note: It might differ if there were already some links, or if subject was null but query filtered not null
                // However, for a fresh migration, they should be equal if 1-to-1 map.
                // Let's allow a small margin if rerunning, but warn.

                if ($linksCreated === 0 && $totalQuestions > 0) {
                    $this->error("CRITICAL: No links were created!");
                    return 1;
                }

                if ($linksCreated < $totalQuestions) {
                    $this->warn("WARNING: Links created ($linksCreated) is less than Total Questions ($totalQuestions). Some questions might have failed or were already linked.");
                }
            }
            else {
                $this->info("SUCCESS: Integrity Check Passed (1:1 mapping confirmed).");
            }

        }
        catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration Failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
