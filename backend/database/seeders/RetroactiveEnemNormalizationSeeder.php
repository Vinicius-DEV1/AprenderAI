<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetroactiveEnemNormalizationSeeder extends Seeder
{
    public function run(): void
    {
        $questions = Question::where('source', 'enem_api')->get();

        foreach ($questions as $question) {
            // A lógica anterior salvava a discipline (área) como subject.
            // Precisamos inverter isso:
            // 1. Pegar o subject atual
            $currentSubject = $question->subjects()->first();

            if (!$currentSubject)
                continue;

            $knowledgeArea = $currentSubject->name; // Ex: "linguagens"

            // 2. Extrair o idioma se for Linguagens
            $subjectName = 'Geral';
            if (Str::lower($knowledgeArea) === 'linguagens') {
                if (Str::contains(Str::lower($question->theme), 'ingles')) {
                    $subjectName = 'Inglês';
                } elseif (Str::contains(Str::lower($question->theme), 'espanhol')) {
                    $subjectName = 'Espanhol';
                } else {
                    $subjectName = 'Português';
                }
            } else {
                // Para outras áreas, o subject real costuma estar no 'theme' ou era genérico
                $subjectName = $question->theme ?: 'Geral';
            }

            // 3. Criar/Encontrar o subject correto
            $newSubject = Subject::firstOrCreate(
                ['name' => $subjectName, 'type' => 'enem'],
                ['slug' => Str::slug($subjectName . '-enem')]
            );

            // 4. Atualizar a questão
            $question->update(['knowledge_area' => $knowledgeArea]);

            // 5. Trocar o relacionamento pivot
            $question->subjects()->sync([$newSubject->id]);
        }
    }
}
