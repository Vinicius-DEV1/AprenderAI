<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Models\WritingRule;
use App\Http\Resources\EssayResource;
use App\Services\EssayImageExtractorService;
use Illuminate\Http\Request;

class EssayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $essays = $user->essays()->latest()->paginate(10);

        // Chart Datasets
        $chartEssays = Essay::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->take(24)
            ->get()
            ->sortBy('created_at');

        $enemSeries = $chartEssays->where('type', 'enem')->take(12)->values()->map(function ($e) {
            return ['date' => $e->created_at->format('d/m'), 'value' => $e->score];
        })->toArray();

        $concursosSeries = $chartEssays->where('type', 'concurso')->take(12)->values()->map(function ($e) {
            return ['date' => $e->created_at->format('d/m'), 'value' => $e->score];
        })->toArray();

        // Used by frontend (Dashboard/EssayList) rules
        $hasEnem = $chartEssays->where('type', 'enem')->isNotEmpty();
        $hasConcursos = $chartEssays->where('type', 'concurso')->isNotEmpty();

        // Empty state fallback - just to make sure graphs show correctly when there's only 1 type
        if (!$hasEnem && $hasConcursos) {
            $hasEnem = false;
        }

        return response()->json([
            'data' => EssayResource::collection($essays),
            'meta' => [
                'current_page' => $essays->currentPage(),
                'last_page' => $essays->lastPage(),
                'total' => $essays->total(),
                'charts' => [
                    'enemSeries' => $enemSeries,
                    'concursosSeries' => $concursosSeries,
                    'hasEnem' => $hasEnem,
                    'hasConcursos' => $hasConcursos
                ],
                'essayLimit' => [
                    'can_create' => $user->canCreateEssay(),
                    'total' => $user->essayQuotaLimit(),
                    'remaining' => max(0, $user->essayQuotaLimit() - $user->monthlyEssayUsed()),
                ]
            ]
        ]);
    }

    public function show(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        $essay->load(['correction']);
        return new EssayResource($essay);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->canCreateEssay()) {
            return response()->json(['message' => 'Você atingiu o limite de redações do seu plano.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:enem,concurso',
            'time_limit' => 'required|integer|in:30,45,60,90,120',
        ]);

        $essay = $user->essays()->create([
            'type' => $validated['type'],
            'time_limit' => $validated['time_limit'] * 60, // save in seconds
            'title' => 'Gerando tema...',
            'content' => '',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        // Draft created successfully
        return new EssayResource($essay);
    }

    public function update(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $essay->update(['content' => $validated['content']]);

        return new EssayResource($essay);
    }

    public function startTopicGeneration(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        \App\Jobs\GenerateEssayTopicJob::dispatch($essay->id);

        return response()->json(['message' => 'Geração de tema iniciada.']);
    }

    public function getTopicStatus(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'status' => $essay->status,
            'title' => $essay->title,
            'topic_description' => $essay->topic_description,
            'theme' => $essay->theme,
        ]);
    }

    public function submit(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        // Validate text OR image
        $request->validate([
            'content' => 'required_without:image|string|nullable',
            'image' => 'required_without:content|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('essays', 'public');
            $essay->input_type = 'image';
            $essay->image_path = $path;
            // The job will do the OCR
        } else {
            $essay->input_type = 'text';
            $essay->content = $request->input('content');
        }

        $essay->status = 'evaluating';
        $essay->submitted_at = now();
        $essay->save();

        // Record usage for limit calculations upon successful submission
        $request->user()->incrementEssayUsage();

        \App\Jobs\EvaluateEssayJob::dispatch($essay);

        return new EssayResource($essay);
    }

    public function retryEvaluation(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($essay->status !== 'error') {
            return response()->json(['message' => 'Esta redação não está em estado de erro.'], 400); // UI expects 400
        }

        $essay->update([
            'status' => 'evaluating',
            'ocr_status' => $essay->input_type === 'image' ? 'processing' : 'completed',
            'ocr_error' => null
        ]);

        \App\Jobs\EvaluateEssayJob::dispatch($essay);

        return new EssayResource($essay);
    }

    /**
     * Returns WritingRule limits for a given essay type (enem|concurso).
     * Used by the React frontend Step 3 to enforce character/line limits.
     */
    public function getRule(Request $request, string $type)
    {
        $rule = \App\Models\WritingRule::where('type', $type)->first();

        return response()->json([
            'min_chars' => $rule?->min_chars ?? 1500,
            'max_chars' => $rule?->max_chars ?? ($type === 'enem' ? 3000 : 4000),
            'max_lines' => $rule?->max_lines ?? 30,
        ]);
    }
}
