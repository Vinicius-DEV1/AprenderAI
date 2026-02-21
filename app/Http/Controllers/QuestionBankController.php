<?php

/**
 * QuestionBankController — Controlador do Banco de Questões (Aluno)
 * ==================================================================
 *
 * Responsável por:
 * - Exibir a página principal com filtros e questões paginadas
 * - Processar respostas individuais (AJAX)
 * - Fornecer estatísticas para gráficos (AJAX)
 * - Fornecer histórico de respostas por questão (AJAX)
 *
 * PADRÃO ARQUITETURAL:
 * Controller "lean" — toda a lógica de negócio é delegada para
 * QuestionService (busca/filtros) e StatsService (métricas).
 * O controller apenas coordena as chamadas e retorna a resposta.
 */

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\UserQuestionAnswer;
use App\Services\QuestionService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    protected QuestionService $questionService;
    protected StatsService $statsService;

    /**
     * Injeção de dependência dos serviços.
     * O Laravel resolve automaticamente via Service Container.
     */
    public function __construct(QuestionService $questionService, StatsService $statsService)
    {
        $this->questionService = $questionService;
        $this->statsService = $statsService;
    }

    /**
     * Página principal do banco de questões.
     *
     * FLUXO:
     * 1. Busca questões filtrando pelos parâmetros do request
     * 2. Carrega opções de filtro para popular os selects
     * 3. Calcula estatísticas resumidas do aluno
     * 4. Mapeia quais questões o aluno já respondeu (para exibir estado na UI)
     * 5. Renderiza a view com todos os dados
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Busca questões com filtros dinâmicos
        $questions = $this->questionService->search($request, $user->id);

        // Mapa [question_id => is_correct] das questões já respondidas
        $answeredMap = UserQuestionAnswer::forUser($user->id)
            ->pluck('is_correct', 'question_id')
            ->toArray();

        // Se for uma requisição AJAX, retorna apenas o partial da lista
        if ($request->ajax()) {
            return view('questions._list', compact('questions', 'answeredMap'));
        }

        // Opções de filtro iniciais
        $filterOptions = $this->questionService->getFilterOptions();
        $overview = $this->statsService->getOverview($user->id);

        return view('questions.index', compact(
            'questions',
            'filterOptions',
            'overview',
            'answeredMap'
        ));
    }

    /**
     * Retorna as matérias filtradas pelo tipo (AJAX).
     *
     * LÓGICA DE NEGÓCIO:
     * Isolamento de Domínio. Só retorna matérias que tenham questões
     * do tipo selecionado.
     */
    public function subjects(Request $request)
    {
        $type = $request->get('type');

        $subjects = \App\Models\Subject::query()
            ->when($type, function($q) use ($type) {
                $q->whereHas('questions', fn($q2) => $q2->filterByType($type));
            })
            ->orderBy('name')
            ->pluck('name');

        return response()->json($subjects);
    }

    /**
     * Retorna os tópicos ou temas filtrados por matéria (AJAX).
     * 
     * LÓGICA DE NEGÓCIO:
     * Busca Topics que possuem questões do Type e Subject especificados.
     * Retorna o ID e Nome para preencher o select corretamente e bater no scope.
     */
    public function topics(Request $request)
    {
        $subjectName = $request->get('subject');
        $type = $request->get('type');
        
        $topics = \App\Models\Topic::query()
            ->whereHas('questions', function($q) use ($type, $subjectName) {
                $q->filterByType($type)->filterBySubject($subjectName);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($topics);
    }

    /**
     * Responde uma questão avulsa (endpoint AJAX).
     *
     * VALIDAÇÃO:
     * - selected_answer: obrigatório, 1 caractere, deve ser A-E
     *
     * RETORNO (JSON):
     * - correct: boolean
     * - correct_answer: string (gabarito)
     * - explanation: string (resolução comentada, pode ser null)
     * - difficulty: string
     * - difficulty_reasoning: string (justificativa da dificuldade)
     */
    public function answer(Request $request, Question $question)
    {
        $request->validate([
            'selected_answer' => 'required|string|size:1|in:A,B,C,D,E',
        ]);

        $feedback = $this->questionService->answerQuestion(
            $request->user()->id,
            $question,
            $request->selected_answer
        );

        return response()->json($feedback);
    }

    /**
     * Estatísticas detalhadas do aluno (endpoint AJAX).
     *
     * Retorna dados formatados para Chart.js:
     * - overview:      total, acertos, erros, taxa de acerto
     * - bySubject:     performance por matéria (bar chart)
     * - temporal:      evolução dos últimos 30 dias (line chart)
     * - byDifficulty:  heatmap de dificuldade (doughnut chart)
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'overview' => $this->statsService->getOverview($userId),
            'bySubject' => $this->statsService->getPerformanceBySubject($userId),
            'temporal' => $this->statsService->getTemporalEvolution($userId),
            'byDifficulty' => $this->statsService->getDifficultyHeatmap($userId),
        ]);
    }

    /**
     * Histórico individual de respostas do aluno para uma questão (endpoint AJAX).
     *
     * PROPÓSITO:
     * Permite que o aluno veja quantas vezes respondeu uma questão específica,
     * quando respondeu e se acertou ou errou. Carregado sob demanda (AJAX)
     * para não impactar o carregamento inicial da lista.
     *
     * RETORNO (JSON):
     * Array com os registros de resposta do aluno para a questão, incluindo:
     * - selected_answer: letra selecionada
     * - is_correct: boolean
     * - answered_at: data/hora da resposta
     */
    public function history(Request $request, Question $question)
    {
        $userId = $request->user()->id;

        // Busca todos os registros de resposta do aluno para esta questão.
        // Como usamos updateOrCreate no answerQuestion, normalmente há 1 registro,
        // mas a query suporta múltiplos caso a lógica futura permita re-respostas.
        $history = UserQuestionAnswer::where('user_id', $userId)
            ->where('question_id', $question->id)
            ->orderByDesc('answered_at')
            ->get(['selected_answer', 'is_correct', 'answered_at']);

        return response()->json($history);
    }
}
