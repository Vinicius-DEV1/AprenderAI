<?php

/**
 * QuestionService — Serviço de Busca e Resolução de Questões
 * ============================================================
 *
 * Responsável por:
 * 1. Busca dinâmica com filtros cumulativos (usando Eloquent when())
 * 2. Fornecer opções de filtro para os dropdowns da UI
 * 3. Registrar respostas individuais do aluno
 *
 * PRINCÍPIO DE DESIGN:
 * Os filtros são 100% opcionais e cumulativos. Se apenas um filtro
 * for selecionado (ex: tipo=enem), todos os outros são ignorados
 * e o sistema retorna TODAS as questões daquela categoria.
 *
 * RELAÇÃO COM O BANCO:
 * - questions: tabela principal (type, year, difficulty, organization, etc.)
 * - subjects: matérias (Many-to-Many via pivot question_subject)
 * - user_question_answers: respostas do aluno (One-to-Many)
 */

namespace App\Services;

use App\Models\Question;
use App\Models\UserQuestionAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class QuestionService
{
    /**
     * Busca questões com filtros dinâmicos e cumulativos.
     *
     * COMO FUNCIONA:
     * Cada ->when() só aplica o filtro se o campo estiver preenchido (filled).
     * Isso garante que a query nunca é restritiva demais — se o aluno
     * só selecionar "Tipo = ENEM", recebe TODAS as questões do ENEM.
     *
     * FILTROS DISPONÍVEIS:
     * - keyword:      busca textual no enunciado (LIKE)
     * - type:         tipo da questão (enem | concurso)
     * - subject:      matéria via relação M2M (whereHas)
     * - topic:        assunto/tópico (coluna string na tabela questions)
     * - year:         ano da questão
     * - difficulty:   dificuldade (easy | medium | hard)
     * - organization: banca examinadora (só p/ concurso)
     * - institution:  órgão público (só p/ concurso)
     * - role:         cargo (só p/ concurso)
     * - status:       filtro de resposta do aluno (respondida/não/acertou/errou)
     *
     * @param Request  $request  HTTP request com os filtros
     * @param int|null $userId   ID do aluno logado (p/ filtro de status)
     * @return LengthAwarePaginator
     */
    public function search(Request $request, ?int $userId = null): LengthAwarePaginator
    {
        $query = Question::with('subjects')
            // ── Filtro de Publicação (Módulo de Importação) ──
            // Exclui questões importadas que ainda estão pendentes de revisão (review_status = 'pending').
            // Questões manuais (sem review_status) são SEMPRE visíveis — backward compatible.
            ->published()

            // ── Busca por palavra-chave no enunciado ──
            // Usa LIKE para encontrar trechos no texto da questão.
            ->when($request->filled('keyword'), fn($q) =>
        $q->where('statement', 'like', '%' . $request->keyword . '%')
        )

            // ── Filtro de Tipo (enem | concurso) ──
            // Este é o "filtro mestre" — determina quais campos fazem
            // sentido na UI (ex: Banca só aparece se tipo=concurso).
            ->when($request->filled('type'), fn($q) =>
        $q->where('type', $request->type)
        )

            // ── Filtro de Matéria (Subject) ──
            // Usa whereHas para buscar na relação Many-to-Many via pivot.
            // Busca pelo nome exato da matéria na tabela subjects.
            ->when($request->filled('subject'), fn($q) =>
        $q->whereHas('subjects', fn($s) =>
        $s->where('subjects.name', $request->subject)
        )
        )

            // ── Filtro de Assunto/Tópico ──
            // Coluna string simples na tabela questions.
            // Ex: "Trigonometria", "Funções de 1º Grau"
            ->when($request->filled('topic'), fn($q) =>
        $q->where('topic', $request->topic)
        )

            // ── Filtro de Ano ──
            ->when($request->filled('year'), fn($q) =>
        $q->where('year', $request->year)
        )

            // ── Filtro de Dificuldade ──
            ->when($request->filled('difficulty'), fn($q) =>
        $q->where('difficulty', $request->difficulty)
        )

            // ── Filtros exclusivos de Concurso ──
            // Estes campos só existem em questões de concurso.
            // São ignorados automaticamente se type=enem (pela condição &&).
            // Banca examinadora (ex: CESPE, FCC, FGV)
            ->when($request->filled('organization') && $request->type !== 'enem', fn($q) =>
        $q->where('organization', $request->organization)
        )
            // Órgão público (ex: TRF, STF, INSS)
            ->when($request->filled('institution') && $request->type !== 'enem', fn($q) =>
        $q->where('institution', $request->institution)
        )
            // Cargo (ex: Analista Judiciário, Técnico)
            ->when($request->filled('role') && $request->type !== 'enem', fn($q) =>
        $q->where('role', $request->role)
        )

            // ── Filtro de Status (requer aluno logado) ──
            // Permite filtrar por questões que o aluno já respondeu,
            // acertou, errou ou ainda não tentou.
            ->when($request->filled('status') && $userId, function ($q) use ($request, $userId) {
            match ($request->status) {
                    'unanswered' => $q->whereDoesntHave('userAnswers', fn($r) => $r->where('user_id', $userId)),
                    'correct' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)->where('is_correct', true)),
                    'incorrect' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)->where('is_correct', false)),
                    'answered' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)),
                    default => null,
                };
        })

            // ── Ordenação padrão: mais recentes primeiro ──
            ->orderByDesc('id');

        // Pagina em blocos de 15 questões, preservando os query params na URL
        return $query->paginate(15)->withQueryString();
    }

    /**
     * Retorna as opções disponíveis para os dropdowns de filtro da UI.
     *
     * DESIGN:
     * Todos os selects são populados com valores DISTINCT do banco,
     * garantindo que só apareçam opções que realmente existem.
     * Isso evita que o aluno selecione um filtro que retorne 0 resultados.
     *
     * NOTA SOBRE SUBJECTS:
     * Após a migration de merge, existem apenas 2 matérias (Matemática e
     * Português). Elas são compartilhadas entre ENEM e Concurso, então
     * não precisamos de separação por tipo — a lista é única.
     *
     * @return array  Arrays associativos com as opções de cada filtro
     */
    public function getFilterOptions(): array
    {
        return [
            // Lista de matérias — busca na tabela subjects (após merge, sem duplicatas)
            'subjects' => \App\Models\Subject::orderBy('name')->pluck('name'),

            // Lista de assuntos/tópicos — distinct da coluna `topic` da tabela questions
            // Ex: "Trigonometria", "Interpretação de Texto", "Funções"
            'topics' => Question::select('topic')
            ->whereNotNull('topic')->where('topic', '!=', '')
            ->distinct()->orderBy('topic')->pluck('topic'),

            // Lista de anos disponíveis (ordem decrescente: mais recente primeiro)
            'years' => Question::select('year')
            ->whereNotNull('year')
            ->distinct()->orderByDesc('year')->pluck('year'),

            // Lista de bancas examinadoras (só existem em questões de concurso)
            'organizations' => Question::select('organization')
            ->whereNotNull('organization')->where('organization', '!=', '')
            ->distinct()->orderBy('organization')->pluck('organization'),

            // Lista de órgãos públicos
            'institutions' => Question::select('institution')
            ->whereNotNull('institution')->where('institution', '!=', '')
            ->distinct()->orderBy('institution')->pluck('institution'),

            // Lista de cargos
            'roles' => Question::select('role')
            ->whereNotNull('role')->where('role', '!=', '')
            ->distinct()->orderBy('role')->pluck('role'),
        ];
    }

    /**
     * Registra a resposta do aluno para uma questão avulsa (fora de simulado).
     *
     * LÓGICA:
     * - Usa updateOrCreate para permitir que o aluno refaça a questão.
     *   A chave [user_id, question_id] garante 1 registro por questão por aluno.
     * - Se o aluno já respondeu, o registro é atualizado com a nova resposta.
     * - Retorna os dados de feedback para exibição imediata na UI.
     *
     * @param int      $userId          ID do aluno
     * @param Question $question        Questão sendo respondida
     * @param string   $selectedAnswer  Letra selecionada (A, B, C, D, E)
     * @return array   Dados de feedback para o frontend
     */
    public function answerQuestion(int $userId, Question $question, string $selectedAnswer): array
    {
        // Verifica se a resposta está correta comparando com o gabarito
        $isCorrect = $question->isCorrect($selectedAnswer);

        // Grava cada tentativa como um novo registro (histórico completo)
        UserQuestionAnswer::create([
            'user_id' => $userId,
            'question_id' => $question->id,
            'selected_answer' => strtoupper($selectedAnswer),
            'is_correct' => $isCorrect,
            'answered_at' => now(),
        ]);

        // Retorna os dados para exibição de feedback na interface
        return [
            'correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'difficulty' => $question->difficulty,
            'difficulty_reasoning' => $question->difficulty_reasoning,
        ];
    }
}
