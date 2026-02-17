<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;

class ImportConcursosCommand extends Command
{
    protected $signature = 'import:concursos {--dry-run : Simulate data import without inserting}';
    protected $description = 'Import questions from external SQLite database';

    public function handle()
    {
        $dbPath = base_path('banco_provas_completo.db');

        if (!file_exists($dbPath)) {
            $this->error("Database file not found at: $dbPath");
            return 1;
        }

        $this->info("Connecting to SQLite database...");

        try {
            $sqlite = new PDO("sqlite:$dbPath");
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (\Exception $e) {
            $this->error("Connection failed: " . $e->getMessage());
            return 1;
        }

        // Count total for progress bar
        $total = $sqlite->query("SELECT COUNT(*) FROM questoes")->fetchColumn();
        $this->info("Found $total questions to process.");

        $query = "
            SELECT 
                q.id as q_id, 
                q.enunciado, 
                q.gabarito,
                q.disciplina,
                p.ano, 
                p.orgao, 
                p.instituicao, 
                p.cargo 
            FROM questoes q 
            JOIN provas p ON q.prova_id = p.id
        ";

        $stmt = $sqlite->query($query);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $skipped = 0;
        $errors = 0;

        $isDryRun = $this->option('dry-run');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                // 1. Prepare Data
                $externalId = "CONCURSO_" . $row['q_id'];

                // Check duplicate
                if (Question::where('external_id', $externalId)->exists()) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // 2. Parse Content (Statement vs Alternatives)
                $parsed = $this->parseContent($row['enunciado']);

                if (!$parsed) {
                    // Log error and skip if can't parse alternatives properly 
                    // (Strict mode per user request for "integrity")
                    Log::warning("Failed to parse alternatives for Question ID {$row['q_id']}");
                    $errors++;
                    $bar->advance();
                    continue;
                }

                // 3. Map Fields
                // The 'subject' field is now handled via a relationship, so it's removed from the direct Question creation data.
                // The 'source' field is updated as per the provided snippet.
                $questionData = [
                    'type' => 'concurso',
                    'statement' => $parsed['statement'],
                    'alternatives' => $parsed['alternatives'], // map to json
                    'correct_answer' => strtoupper(trim($row['gabarito'])),
                    'explanation' => null, // Added as per snippet
                    'year' => (int)$row['ano'],
                    'difficulty' => 'medium',
                    'source' => 'sql_import', // Changed from 'generated_system'
                    'organization' => $row['instituicao'], // Banca maps to organization
                    'institution' => $row['orgao'], // Orgao maps to institution
                    'role' => $row['cargo'],
                    'external_id' => $externalId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (!$isDryRun) {
                    $question = Question::create($questionData);

                    // Attach subject (N:N Relationship)
                    // We normalize the subject name and attach it to the question using the pivot table.
                    $mapDisciplina = $row['disciplina'] ?? 'Geral';
                    $subjectModel = Subject::firstOrCreate(
                    ['name' => $mapDisciplina],
                    ['slug' => Str::slug($mapDisciplina), 'type' => 'concurso']
                    );
                    $question->subjects()->attach($subjectModel->id);
                }

                $inserted++;

            }
            catch (\Exception $e) {
                Log::error("Error importing Question {$row['q_id']}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Import finished.");
        $this->info("Inserted: $inserted");
        $this->info("Skipped (Duplicate): $skipped");
        $this->info("Errors/Parse Failed: $errors");

        if ($isDryRun) {
            $this->info("NOTE: This was a DRY RUN. No data was inserted.");
        }

        return 0;
    }

    private function parseContent($rawText)
    {
        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);

        // Regex to find alternatives starting with (A), (B) etc OR A), B) etc
        // Matches (A) content (B) content ...
        // We look for the FIRST occurrence of (A) or A) to split statement from alternatives

        $pattern = '/\n\s*(?:\(|^)\s*[A-E]\s*(?:\)|\.)/m'; // Matches start of an alternative

        // Split text by pattern, preserving delimiters to know which letter it is
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

        // If we don't have at least statement + 2 alternatives, detection failed.
        // Actually preg_split won't give us the delimiters if we don't use () in regex
        // Let's use a different approach. Match all alternatives.

        $alternatives = [];
        $statement = $text;

        // Find all alternatives
        // Pattern: Starts with (Letter) or Letter) or Letter. at start of line
        // Capture Letter and Content

        // Strategy: Find position of first (A) or A)
        // Everything before is Statement.
        // Everything after is parsed as alternatives.

        // Match A followed by close paren or dot
        if (preg_match('/(?:^|\n)\s*(?:\()?([A-E])(?:\)|\.)\s+/m', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $firstAltPos = $matches[0][1];

            // Statement is everything before matches[0][1]
            $statement = trim(substr($text, 0, $firstAltPos));
            $rest = substr($text, $firstAltPos);

            // Now parse alternatives from $rest
            // Split by Lookahead for Next Alternative
            // Split by (Start of Line) + (Letter) + (Sep)

            $blocks = preg_split('/(?:^|\n)\s*(?:\(?([A-E])(?:\)|\.))\s+/m', $rest, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            // Expected structure: [Letter, Content, Letter, Content...]
            // Because the first split is expected to be empty text before first A (handled by offset subtr)

            for ($i = 0; $i < count($blocks); $i += 2) {
                if (isset($blocks[$i]) && isset($blocks[$i + 1])) {
                    $letter = trim(str_replace(['(', ')', '.'], '', $blocks[$i]));
                    $content = trim($blocks[$i + 1]);
                    $alternatives[$letter] = $content;
                }
            }
        }
        else {
            // Cannot find structured alternatives
            return null;
        }

        // Validation: Must have at least A and B
        if (!isset($alternatives['A']) || !isset($alternatives['B'])) {
            return null;
        }

        return [
            'statement' => $statement,
            'alternatives' => $alternatives
        ];
    }
}
