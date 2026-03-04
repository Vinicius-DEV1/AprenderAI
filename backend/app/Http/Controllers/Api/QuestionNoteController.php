<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionNoteController extends Controller
{
    /**
     * Lista as anotações do usuário para uma questão.
     */
    public function index(Question $question)
    {
        $notes = $question->notes()->where('user_id', Auth::id())->latest()->get();
        return response()->json($notes);
    }

    /**
     * Cria uma nova anotação.
     */
    public function store(Request $request, Question $question)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note = $question->notes()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Anotação salva com sucesso.',
            'note' => $note
        ], 201);
    }

    /**
     * Atualiza uma anotação existente.
     */
    public function update(Request $request, QuestionNote $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note->update($validated);

        return response()->json([
            'message' => 'Anotação atualizada com sucesso.',
            'note' => $note
        ]);
    }

    /**
     * Exclui uma anotação.
     */
    public function destroy(QuestionNote $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $note->delete();

        return response()->json(['message' => 'Anotação excluída com sucesso.']);
    }
}
