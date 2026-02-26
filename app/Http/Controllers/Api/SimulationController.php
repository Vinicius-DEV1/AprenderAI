<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulation;
use App\Http\Resources\SimulationResource;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function index(Request $request)
    {
        $simulations = $request->user()->simulations()->latest()->paginate(10);
        return SimulationResource::collection($simulations);
    }

    public function show(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $simulation->load(['answers.question.alternatives', 'answers.question.subjects']);

        return new SimulationResource($simulation);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject_ids' => 'array',
            'topic_ids' => 'array',
            'questions_count' => 'integer|min:1|max:90',
            'type' => 'nullable|string|in:concurso,enem,vestibular,custom',
            'include_essay' => 'boolean',
        ]);

        $simulation = $request->user()->simulations()->create([
            'title' => $validated['title'],
            'status' => 'generating',
            'questions_count' => $validated['questions_count'] ?? 10,
            'configuration' => [
                'type' => $validated['type'] ?? 'custom',
                'include_essay' => $validated['include_essay'] ?? false,
                'subject_ids' => $validated['subject_ids'] ?? [],
                'topic_ids' => $validated['topic_ids'] ?? [],
            ]
        ]);

        // In a real scenario, this would dispatch a generation job
        \App\Jobs\GenerateSimulationQuestions::dispatch($simulation, $validated);

        return new SimulationResource($simulation);
    }

    public function status(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $simulation->refresh();
        $answersCount = $simulation->answers()->count();

        return response()->json([
            'simulation_status' => $simulation->status,
            'answers_count' => $answersCount,
            'is_finished' => $simulation->isFinished(),
        ]);
    }

    public function answer(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'nullable|string|size:1',
            'marked_for_review' => 'boolean',
            'time_spent' => 'integer|min:0',
        ]);

        $answer = $simulation->answers()
            ->where('question_id', $validated['question_id'])
            ->first();

        if (!$answer) {
            return response()->json(['error' => 'Question not found in this simulation'], 404);
        }

        $answer->update([
            'user_answer' => $validated['answer'],
            'marked_for_review' => $validated['marked_for_review'] ?? $answer->marked_for_review,
            'time_spent' => $validated['time_spent'] ?? 0,
            'is_correct' => $answer->question->isCorrect($validated['answer'] ?? ''),
        ]);

        return response()->json(['success' => true]);
    }

    public function finish(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $simulation->finishSimulation();

        return new SimulationResource($simulation);
    }

    public function submit(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.alternative_id' => 'required|integer',
            'time_spent' => 'integer',
        ]);

        $simulation->update([
            'status' => 'completed',
            'time_spent' => $validated['time_spent'] ?? $simulation->time_spent,
            'completed_at' => now(),
        ]);

        return new SimulationResource($simulation);
    }
}
