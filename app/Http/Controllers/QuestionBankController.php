<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\QuestionService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    protected QuestionService $questionService;
    protected StatsService $statsService;

    public function __construct(QuestionService $questionService, StatsService $statsService)
    {
        $this->questionService = $questionService;
        $this->statsService = $statsService;
    }

    /**
     * Página principal do banco de questões com filtros.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Questões filtradas e paginadas
        $questions = $this->questionService->search($request, $user->id);

        // Opções de filtro para a UI
        $filterOptions = $this->questionService->getFilterOptions();

        // Stats do aluno
        $overview = $this->statsService->getOverview($user->id);

        // IDs das questões já respondidas pelo usuário (para exibir estado na UI)
        $answeredMap = \App\Models\UserQuestionAnswer::forUser($user->id)
            ->pluck('is_correct', 'question_id')
            ->toArray();

        return view('questions.index', compact(
            'questions',
            'filterOptions',
            'overview',
            'answeredMap'
        ));
    }

    /**
     * Responde uma questão avulsa (AJAX).
     */
    public function answer(Request $request, Question $question)
    {
        $request->validate([
            'selected_answer' => 'required|string|size:1|in:A,B,C,D,E',
        ]);

        $user = $request->user();

        $feedback = $this->questionService->answerQuestion(
            $user->id,
            $question,
            $request->selected_answer
        );

        return response()->json($feedback);
    }

    /**
     * Estatísticas do aluno (AJAX — dados para Chart.js).
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
}
