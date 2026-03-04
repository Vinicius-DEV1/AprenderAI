<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Alterna o status de favorito de uma questão para o usuário logado.
     */
    public function toggle(Request $request, Question $question)
    {
        $user = Auth::user();

        $favorite = $user->favorites()->where('question_id', $question->id)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json([
                'is_favorite' => false,
                'message' => 'Questão removida dos favoritos.',
            ]);
        }

        $user->favorites()->create([
            'question_id' => $question->id,
        ]);

        return response()->json([
            'is_favorite' => true,
            'message' => 'Questão adicionada aos favoritos.',
        ]);
    }
}
