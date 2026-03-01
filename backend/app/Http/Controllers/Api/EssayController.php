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

        $hasEnem = Essay::where('user_id', $user->id)->where('type', 'enem')->exists();
        $hasConcursos = Essay::where('user_id', $user->id)->where('type', 'concurso')->exists();

        if (!$hasEnem && !$hasConcursos) {
            $hasEnem = true;
        }

        return response()->json([
            'data' => EssayResource::collection($essays),
            'meta' => [
                'current_page' => $essays->currentPage(),
                'last_page' => $essays->lastPage(),
                'per_page' => $essays->perPage(),
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

        // Load specific correction details if present
        $essay->load(['correction']);

        return new EssayResource($essay);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:enem,concurso',
            'time_limit' => 'required|integer|in:30,45,60,90,120',
        ]);

        $user = $request->user();

        // Create initial draft
        $essay = Essay::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'time_limit' => $validated['time_limit'],
            'title' => 'Gerando tema...',
            'content' => '',
            'status' => 'in_progress',
            'topic_regen_count' => 0,
            'started_at' => now(),
        ]);

        return new EssayResource($essay);
    }

    public function startTopicGeneration(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id)
            abort(403);

        if ($essay->topic_regen_count >= 3) {
            return response()->json([
                'message' => 'Limite de tentativas atingido.'
            ], 429);
        }

        \App\Jobs\GenerateEssayTopicJob::dispatch($essay->id);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($essay->status !== 'in_progress') {
            return response()->json(['message' => 'Apenas redações em andamento podem ser editadas.'], 400);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
        ]);

        $essay->update([
            'content' => $validated['content'] ?? '',
        ]);

        return new EssayResource($essay);
    }

    public function getTopicStatus(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id)
            abort(403);

        return response()->json([
            'status' => $essay->status,
            'title' => $essay->title,
            'topic_description' => $essay->topic_description,
            'topic_regen_count' => $essay->topic_regen_count
        ]);
    }

    public function submit(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id)
            abort(403);

        if ($essay->submitted_at) {
            return response()->json(['message' => 'Redação já enviada'], 400);
        }

        $validated = $request->validate([
            'input_type' => 'required|in:text,image',
            'content' => 'required_if:input_type,text|nullable|string|min:50',
            'image' => 'required_if:input_type,image|nullable|image|mimes:jpeg,png,webp|max:8192',
            'custom_theme' => 'nullable|string|max:255'
        ]);

        // --- MODELO ACUMULATIVO: Debita a Quota primeiro usando Lock no Banco ---
        try {
            // Se o usuário tem admin override, não passamos pelo gateway
            if (is_null($request->user()->max_essays_override)) {
                app(\App\Services\QuotaService::class)->consumeQuota($request->user(), 'essays', 1);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        // --- Prossegue se a quota permitiu ---
        // If user provided a custom theme manually instead of AI
        if (!empty($validated['custom_theme'])) {
            $essay->title = $validated['custom_theme'];
            $essay->topic_description = $validated['custom_theme'];
        }

        if ($request->input_type === 'image' && $request->hasFile('image')) {
            $path = $request->file('image')->store('essays_images', 'public');

            try {
                $ocrService = app(\App\Services\EssayImageExtractorService::class);
                $publicUrl = asset('storage/' . $path);

                $extractedText = $ocrService->extractText(storage_path('app/public/' . $path), $publicUrl);

                $essay->input_type = 'image';
                $essay->image_path = $path;
                $essay->content = $extractedText;
                $essay->extracted_text = $extractedText;
                $essay->ocr_status = 'success';
                $essay->status = 'evaluating';
                $essay->submitted_at = now();
                $essay->save();

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('OCR failed in EssayController API', [
                    'reason' => $e->getMessage()
                ]);

                $essay->input_type = 'image';
                $essay->image_path = $path;
                $essay->ocr_status = 'failed';
                $essay->ocr_error = substr($e->getMessage(), 0, 500);
                $essay->status = 'in_progress'; // Back to form
                $essay->save();

                return response()->json([
                    'message' => 'Não foi possível extrair o texto da sua imagem. ' . $e->getMessage() . ' Por favor, tente enviar uma foto mais nítida ou digite.',
                ], 422);
            }
        } else {
            $essay->input_type = 'text';
            $essay->content = $request->input('content');
            $essay->status = 'evaluating';
            $essay->submitted_at = now();
            $essay->save();
        }

        \App\Jobs\EvaluateEssayJob::dispatch($essay);

    }

    public function retryEvaluation(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($essay->status !== 'error') {
            return response()->json(['message' => 'Esta redação não está em estado de erro.'], 400);
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

