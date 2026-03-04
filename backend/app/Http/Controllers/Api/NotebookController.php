<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notebook;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotebookController extends Controller
{
    public function index()
    {
        $notebooks = Auth::user()->notebooks()->withCount('questions')->latest()->get();
        return response()->json($notebooks);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->canCreateNotebook()) {
            return response()->json([
                'message' => 'Seu plano permite apenas 1 caderno. Faça upgrade para criar mais.',
                'error_code' => 'limit_reached'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $notebook = $user->notebooks()->create($validated);

        return response()->json([
            'message' => 'Caderno criado com sucesso.',
            'notebook' => $notebook
        ], 201);
    }

    public function update(Request $request, Notebook $notebook)
    {
        if ($notebook->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $notebook->update($validated);

        return response()->json([
            'message' => 'Caderno atualizado com sucesso.',
            'notebook' => $notebook
        ]);
    }

    public function destroy(Notebook $notebook)
    {
        if ($notebook->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $notebook->delete();

        return response()->json(['message' => 'Caderno excluído com sucesso.']);
    }

    public function addQuestion(Request $request, Notebook $notebook, Question $question)
    {
        if ($notebook->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $notebook->questions()->syncWithoutDetaching([$question->id]);

        return response()->json(['message' => 'Questão adicionada ao caderno.']);
    }

    public function removeQuestion(Request $request, Notebook $notebook, Question $question)
    {
        if ($notebook->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $notebook->questions()->detach($question->id);

        return response()->json(['message' => 'Questão removida do caderno.']);
    }

    /**
     * Sincroniza uma questão com múltiplos cadernos de uma vez.
     * Útil para o frontend enviar um array de notebook_ids de uma vez.
     */
    public function syncQuestion(Request $request, Question $question)
    {
        $validated = $request->validate([
            'notebook_ids' => 'array',
            'notebook_ids.*' => 'integer|exists:notebooks,id',
        ]);

        $user = Auth::user();
        $notebookIds = $validated['notebook_ids'] ?? [];

        // Verifica se todos os cadernos pertencem ao usuário
        $validNotebooks = $user->notebooks()->whereIn('id', $notebookIds)->pluck('id')->toArray();

        // 1. Get all notebook IDs belonging to the user
        $allUserNotebookIds = $user->notebooks()->pluck('id')->toArray();

        // 2. Identify which of the user's notebooks should be DETACHED
        // (those the user owns but were NOT present in the valid requested IDs)
        $notebooksToDetach = array_diff($allUserNotebookIds, $validNotebooks);

        // 3. Perform bulk operations
        if (!empty($notebooksToDetach)) {
            $question->notebooks()->detach($notebooksToDetach);
        }

        if (!empty($validNotebooks)) {
            $question->notebooks()->syncWithoutDetaching($validNotebooks);
        }

        return response()->json(['message' => 'Questão sincronizada com os cadernos selecionados.']);
    }
}
