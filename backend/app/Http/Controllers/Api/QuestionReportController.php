<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionReportController extends Controller
{
    /**
     * (Usuário) Cria um report para uma questão.
     */
    public function store(Request $request, Question $question)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $report = $question->reports()->create([
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Problema reportado com sucesso. Nossa equipe analisará em breve.',
            'report' => $report
        ], 201);
    }

    /**
     * (Admin) Lista as questões reportadas, agrupadas por questão.
     */
    public function index(Request $request)
    {
        // Se desejar listar reports individuais, podemos manter um filtro, 
        // mas o padrão será agrupar por questão.

        $status = $request->status ?? 'pending';

        $reportedQuestions = Question::whereHas('reports', function ($q) use ($status) {
            $q->where('status', $status);
        })
            ->with([
                'reports' => function ($q) use ($status) {
                    $q->where('status', $status)->with('user');
                }
            ])
            ->withCount([
                'reports' => function ($q) use ($status) {
                    $q->where('status', $status);
                }
            ])
            ->orderByDesc('reports_count')
            ->paginate(20);

        return response()->json($reportedQuestions);
    }

    /**
     * (Admin) Marca o report como resolvido.
     */
    public function resolve(QuestionReport $report)
    {
        $report->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Report marcado como resolvido.']);
    }

    /**
     * (Admin) Desativa a questão e resolve os reports.
     */
    public function deactivateQuestion(Question $question)
    {
        $question->update(['is_active' => false]);

        // Resolve todos os reports pendentes desta questão
        $question->reports()->where('status', 'pending')->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Questão desativada com sucesso. Reports resolvidos.']);
    }
}
