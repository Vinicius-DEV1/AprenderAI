<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = Question::query();

        if ($request->filled('search')) {
            $query->where('statement', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        
        // Filter for missing explanations (to help admin prioritize)
        if ($request->boolean('missing_explanation')) {
            $query->whereNull('explanation')->orWhere('explanation', '');
        }

        $questions = $query->orderByDesc('id')->paginate(15);

        return view('admin.questions.index', compact('questions'));
    }

    public function create()
    {
        return view('admin.questions.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|in:matemática,português',
            'type' => 'required|in:enem,concurso',
            'statement' => 'required|string',
            'alternatives' => 'required|array|min:5', // A, B, C, D, E
            'alternatives.A' => 'required|string',
            'alternatives.B' => 'required|string',
            'alternatives.C' => 'required|string',
            'alternatives.D' => 'required|string',
            'alternatives.E' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D,E',
            'explanation' => 'nullable|string',
            'source' => 'required|in:manual,ai_generated',
            'year' => 'nullable|integer',
            'difficulty' => 'required|in:easy,medium,hard',
        ]);

        Question::create($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão criada com sucesso!');
    }

    public function edit(Question $question)
    {
        return view('admin.questions.form', compact('question'));
    }

    public function update(Request $request, Question $question)
    {
         $validated = $request->validate([
            'subject' => 'required|in:matemática,português',
            'type' => 'required|in:enem,concurso',
            'statement' => 'required|string',
            'alternatives' => 'required|array|min:5',
            'alternatives.A' => 'required|string',
            'alternatives.B' => 'required|string',
            'alternatives.C' => 'required|string',
            'alternatives.D' => 'required|string',
            'alternatives.E' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D,E',
            'explanation' => 'nullable|string',
            'source' => 'required|in:manual,ai_generated',
             'year' => 'nullable|integer',
            'difficulty' => 'required|in:easy,medium,hard',
        ]);

        $question->update($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão atualizada com sucesso!');
    }

    public function destroy(Question $question)
    {
        $question->delete();
        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão removida!');
    }
}
