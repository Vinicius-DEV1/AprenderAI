<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Models\WritingRule;
use App\Http\Resources\EssayResource;
use App\Services\EssayImageExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EssayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('plan');

        // Orphan plan protection
        if ($user->plan_id && !$user->plan) {
            \Log::warning("User #{$user->id} has orphan plan_id #{$user->plan_id}. Plan record not found.");
        }

        $essays = $user->essays()->latest()->paginate(10);

        // Chart Datasets - Improved Filtering as per request
        // Using statuses: completed, corrigida, finished
        $chartEssays = $user->essays()
            ->whereNotNull('score')
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

        // Monthly Limit Calculation (Direct from DB as requested)
        $monthlyUsed = Essay::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $limit = $user->essayQuotaLimit();
        $used = $user->monthlyEssayUsed();

        \Log::info('[EssayController::index] quota', [
            'user_id' => $user->id,
            'plan_id' => $user->plan_id,
            'plan_slug' => $user->plan?->slug,
            'limit' => $limit,
            'used' => $used,
        ]);

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
                    'hasConcursos' => $hasConcursos,
                    'enemStats' => $hasEnem ? [
                        'last' => $chartEssays->where('type', 'enem')->last()?->score ?? 0,
                        'prev' => $chartEssays->where('type', 'enem')->slice(-2, 1)->first()?->score ?? 0,
                        'mean' => round($chartEssays->where('type', 'enem')->avg('score') ?? 0),
                        'best' => $chartEssays->where('type', 'enem')->max('score') ?? 0,
                        'total' => $user->essays()->where('type', 'enem')->whereIn('status', ['completed', 'corrigida', 'finished'])->count(),
                    ] : null,
                    'concursoStats' => $hasConcursos ? [
                        'last' => $chartEssays->where('type', 'concurso')->last()?->score ?? 0,
                        'prev' => $chartEssays->where('type', 'concurso')->slice(-2, 1)->first()?->score ?? 0,
                        'mean' => round($chartEssays->where('type', 'concurso')->avg('score') ?? 0),
                        'best' => $chartEssays->where('type', 'concurso')->max('score') ?? 0,
                        'total' => $user->essays()->where('type', 'concurso')->whereIn('status', ['completed', 'corrigida', 'finished'])->count(),
                    ] : null,
                ],
                // ── Single Source of Truth ────────────────────────────────────
                // Keys: total / used / remaining / can_create / plan_name / plan_slug
                // EssayWrite (Wizard) reads: essayLimit.remaining + essayLimit.total
                // EssayList (Dashboard) reads: essayLimit.total + essayLimit.used
                'essayLimit' => [
                    'total' => $limit,
                    'used' => $used,
                    'remaining' => max(0, $limit - $used),
                    'can_create' => $limit === 0 ? false : ($used < $limit),
                    'plan_name' => optional($user->plan)->name,
                    'plan_slug' => optional($user->plan)->slug,
                ],
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

        $validated = $request->validate([
            'type' => 'required|in:enem,concurso',
            'time_limit' => 'required|integer|in:30,45,60,90,120',
        ]);

        // Check essay quota — catch exceptions so they don't silently return false
        try {
            $canCreate = $user->canCreateEssay();
        } catch (\Throwable $e) {
            Log::error('[EssayController::store] Erro ao verificar quota de redação', [
                'user_id' => $user->id,
                'type' => $validated['type'],
                'time_limit' => $validated['time_limit'],
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'code' => 'DRAFT_CREATE_FAILED',
                'message' => 'Falha interna ao verificar sua quota de redações. Tente novamente.',
            ], 500);
        }

        if (!$canCreate) {
            return response()->json([
                'code' => 'QUOTA_EXCEEDED',
                'message' => 'Você atingiu seu limite mensal de redações.',
            ], 403);
        }

        try {
            $essay = $user->essays()->create([
                'type' => $validated['type'],
                'time_limit' => $validated['time_limit'] * 60,
                'title' => 'Gerando tema...',
                'content' => '',
                'status' => 'pending',
                'started_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[EssayController::store] Erro ao criar rascunho', [
                'user_id' => $user->id,
                'type' => $validated['type'],
                'time_limit' => $validated['time_limit'],
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'code' => 'DRAFT_CREATE_FAILED',
                'message' => 'Falha interna ao criar rascunho. Tente novamente.',
            ], 500);
        }

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

        // If status is error, attempt to retrieve structured payload
        $errorPayload = null;
        if ($essay->status === 'error') {
            // 1) Read from Cache
            $cachedError = \Illuminate\Support\Facades\Cache::get("essay:topic:error:{$essay->id}");
            if ($cachedError) {
                $errorPayload = $cachedError;
            }
            // 2) Read from fallback column
            elseif (str_starts_with($essay->topic_description ?? '', '__AI_ERROR__:')) {
                $rawJson = substr($essay->topic_description, 13);
                $decoded = json_decode($rawJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $errorPayload = $decoded;
                }
            }
        }

        return response()->json([
            'status' => $essay->status,
            'title' => $essay->title,
            'topic_description' => $essay->topic_description && !str_starts_with($essay->topic_description, '__AI_ERROR__:') ? $essay->topic_description : null,
            'theme' => $essay->theme,
            'error_details' => $errorPayload
        ]);
    }

    public function submit(Request $request, Essay $essay)
    {
        $requestId = (string) \Illuminate\Support\Str::uuid();

        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        // Validate text OR image
        $request->validate([
            'content' => 'required_without:image|string|nullable',
            'image' => 'required_without:content|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);

        $user = $request->user();
        Log::info('[EssayController::submit] Submission start', [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'essay_id' => $essay->id,
            'type' => $essay->type
        ]);

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $essay, $user, $requestId) {
                // Check if can consume (avoid race conditions)
                if (!$user->canCreateEssay()) {
                    return response()->json([
                        'code' => 'QUOTA_EXCEEDED',
                        'message' => 'Você atingiu o limite mensal de redações.',
                        'request_id' => $requestId
                    ], 403);
                }

                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('essays', 'public');
                    $essay->input_type = 'image';
                    $essay->image_path = $path;
                } else {
                    $essay->input_type = 'text';
                    $essay->content = $request->input('content');
                }

                // Save theme if provided from UI
                if ($request->filled('theme')) {
                    $essay->title = $request->input('theme');
                }

                $essay->status = 'evaluating';
                $essay->submitted_at = now();
                $essay->save();

                // Record usage for limit calculations upon successful submission
                $user->incrementEssayUsage();

                \App\Jobs\EvaluateEssayJob::dispatch($essay);

                $updatedUsage = app(\App\Services\QuotaService::class)->getUsage($user, 'essays');
                $limitRaw = $updatedUsage['limit'] ?? $user->essayQuotaLimit();
                $limit = $this->normalizeLimit($limitRaw);
                $used = (int) ($updatedUsage['used'] ?? 0);

                $monthly_remaining = is_null($limit) ? null : max($limit - $used, 0);

                return (new EssayResource($essay))->additional([
                    'meta' => [
                        'monthly_limit_total' => $limitRaw,
                        'monthly_used' => $used,
                        'monthly_remaining' => $monthly_remaining,
                        'request_id' => $requestId,
                    ]
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[EssayController::submit] Erro fatal ao enviar redação', [
                'request_id' => $requestId,
                'user_id' => $user->id,
                'essay_id' => $essay->id,
                'exception' => $e->getMessage(),
                'type' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'code' => 'SUBMIT_FAILED',
                'message' => 'Erro interno no servidor. Nossa equipe já foi notificada.',
                'request_id' => $requestId,
            ], 500);
        }
    }

    /**
     * Normalizes a quota limit to an integer or null (unlimited).
     */
    private function normalizeLimit($limit): ?int
    {
        if (is_null($limit) || $limit === 'unlimited' || $limit === '∞' || $limit === 9999) {
            return null;
        }

        if (is_numeric($limit)) {
            return (int) $limit;
        }

        return null;
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
        if (!in_array($type, ['enem', 'concurso'])) {
            return response()->json([
                'code' => 'INVALID_TYPE',
                'message' => 'Tipo inválido. Use enem ou concurso.',
            ], 422);
        }

        $rule = WritingRule::where('type', $type)->first();

        return response()->json([
            'min_chars' => $rule?->min_chars ?? 1500,
            'max_chars' => $rule?->max_chars ?? ($type === 'enem' ? 3000 : 4000),
            'max_lines' => $rule?->max_lines ?? 30,
        ]);
    }
}
