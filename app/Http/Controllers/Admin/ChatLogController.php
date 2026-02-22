<?php

namespace App\Http\Controllers\Admin;

use App\Models\AiRequestLog;
use App\Models\QuestionInteraction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ChatLogController extends Controller
{
    public function show($id)
    {
        $log = AiRequestLog::with(['user', 'apiKey'])->findOrFail($id);

        // If question_id is available, fetch the specific conversation context
        // Otherwise, we might default to showing the prompt/response from the log itself
        // But user requested "Histórico da Conversa" so we assume question context.

        $messages = [];
        $question = null;

        if ($log->question_id) {
            $question = \App\Models\Question::find($log->question_id);
            if ($question) {
                // Fetch interaction history for this user/question/simulation context?
                // AiRequestLog doesn't store simulation_id.
                // However, QuestionInteraction stores user_id and question_id.
                // We can fetch all interactions for this question by this user.
                // NOTE: This might mix multiple simulation sessions if the user did the same question twice.
                // Ideally we'd have simulation_id in AiRequestLog too, but for now filtering by time around the log creation?
                // Or just show all history for that question/user which is probably what the admin wants (context).

                $messages = QuestionInteraction::where('user_id', $log->user_id)
                    ->where('question_id', $log->question_id)
                    ->orderBy('created_at', 'asc')
                    ->get();
            }
        }

        return view('admin.chat_logs.show', compact('log', 'messages', 'question'));
    }
}
