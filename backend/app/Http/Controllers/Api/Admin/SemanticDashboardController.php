<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\QdrantService;
use App\Services\AI\SemanticCacheService;
use App\Services\AI\AIService;
use App\Models\Question;
use App\Models\Concept;
use App\Models\QuestionVector;
use App\Models\SearchInteractionLog;
use App\Models\AiSearchCache;
use App\Models\AiSearchRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;


class SemanticDashboardController extends Controller
{
    /**
     * Get overall statistics for the Semantic Dashboard
     */
    public function index(QdrantService $qdrant)
    {
        // 1. MySQL Data
        $totalQuestions = Question::published()->where('tipo_questao', '!=', 'Redação')->count();
        // Direct count — no cache here since this is a real-time monitoring dashboard.
        // Caching hid indexing progress from the admin view.
        $indexedQuestions = QuestionVector::whereHas('question', function ($query) {
            $query->published()->where('tipo_questao', '!=', 'Redação');
        })->distinct('question_id')->count('question_id');
        $totalVectors = QuestionVector::count();
        $totalSubjects = \App\Models\Subject::count();
        $indexedSubjects = \App\Models\Subject::whereNotNull('qdrant_indexed_at')->count();
        
        $totalTopics = \App\Models\Topic::count();
        $indexedTopics = \App\Models\Topic::whereNotNull('qdrant_indexed_at')->count();

        // 2. Qdrant Data (Collections Info)
        $qdrantQuestions = 0;
        $qdrantConcepts = 0;
        $qdrantStatus = 'offline';

        try {
            $questionsCol = $qdrant->getCollectionInfo(config('xavier.qdrant.collections.questions', 'questions_vectors'));
            $qdrantQuestions = $questionsCol['points_count'] ?? 0;

            $conceptsCol = $qdrant->getCollectionInfo(config('xavier.qdrant.collections.concepts', 'concepts_vectors'));
            $qdrantConcepts = $conceptsCol['points_count'] ?? 0;

            $qdrantStatus = 'online';
        } catch (\Exception $e) {
            $qdrantStatus = 'error: ' . $e->getMessage();
        }

        // 3. Cache & Latency Stats (from SearchInteractionLog and AiSearchCache)
        $totalLogSearches = SearchInteractionLog::distinct('ai_search_id')->count('ai_search_id');
        // As cache_type column doesn't exist, we show total L2 entries
        $totalCacheEntries = AiSearchCache::count();
        $l1CacheHits = 0; // Efemero/Redis
        $l2CacheHits = $totalCacheEntries; // Aproximado para o dashboard

        // 4. Jobs Stats (Embeddings Queues)
        $embeddingQueues = [
            config('xavier.embeddings.queue', 'embeddings'),
            config('xavier.search_embeddings.queue', 'search_embeddings')
        ];
        
        $pendingJobs = DB::table('jobs')->whereIn('queue', $embeddingQueues)->count();
        $failedJobs = DB::table('failed_jobs')->whereIn('queue', $embeddingQueues)->count();

        // 5. Detailed Failed Jobs
        $failedJobsDetails = DB::table('failed_jobs')
            ->whereIn('queue', $embeddingQueues)
            ->orderBy('failed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($job) {
                // Extract just the first line of the exception for readability
                $exceptionLines = explode("\n", $job->exception);
                return [
                    'id' => $job->id,
                    'failed_at' => $job->failed_at,
                    'payload' => json_decode($job->payload, true)['displayName'] ?? 'Unknown Job',
                    'error_preview' => $exceptionLines[0] ?? 'Unknown Error',
                ];
            });

        // 6. Recent Searches (from AiSearchRequest which logs user prompts)
        $recentSearches = \App\Models\AiSearchRequest::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user_name' => $req->user ? $req->user->name : 'System/Guest',
                    'prompt' => $req->prompt,
                    'status' => $req->status,
                    'created_at' => \Carbon\Carbon::parse($req->created_at)->format('d/m/Y H:i:s'),
                    'similarity_threshold' => $req->similarity_threshold,
                    'filters' => $req->filters,
                    'error' => $req->error,
                ];
            });

        // 7. Analytics (absorvidos da antiga Xavier Insights page)
        // Taxa de sucesso das buscas + termos mais buscados + gráfico 7 dias
        $totalAiRequests   = AiSearchRequest::count();
        $successAiRequests = AiSearchRequest::where('status', 'completed')->count();
        $successRate       = $totalAiRequests > 0
            ? round(($successAiRequests / $totalAiRequests) * 100, 1)
            : 0;

        $topPrompts = AiSearchRequest::select('prompt', DB::raw('count(*) as total'))
            ->groupBy('prompt')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $chartData = AiSearchRequest::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success"),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 7.5. Sucessos Recentes de Indexação (Embeddings)
        $recentSuccesses = DB::table('question_vectors')
            ->join('questions', 'questions.id', '=', 'question_vectors.question_id')
            ->select([
                'questions.id', 
                'questions.statement', 
                'questions.organization',
                'questions.year',
                'question_vectors.pipeline_version', 
                'question_vectors.indexed_at',
                DB::raw("(SELECT name FROM subjects INNER JOIN question_subject ON subjects.id = question_subject.subject_id WHERE question_subject.question_id = questions.id LIMIT 1) as subject_name")
            ])
            ->orderByDesc('question_vectors.indexed_at')
            ->limit(10)
            ->get()
            ->map(function($q) {
                return [
                    'id' => $q->id,
                    'statement' => Str::limit(strip_tags($q->statement), 120),
                    'organization' => $q->organization,
                    'year' => $q->year,
                    'subject' => $q->subject_name,
                    'pipeline_version' => $q->pipeline_version,
                    'indexed_at' => $q->indexed_at,
                ];
            });

        // 8. Checar versão do Índice do Qdrant (Garante que as coleções existem antes)
        $qdrant->ensureQuestionsCollection();
        $qdrant->ensureConceptsCollection();
        
        $currentPipeline = config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser');
        $questionsVersionCheck = $qdrant->checkIndexVersion(config('xavier.qdrant.collections.questions'), $currentPipeline);
        $conceptsVersionCheck  = $qdrant->checkIndexVersion(config('xavier.qdrant.collections.concepts'), $currentPipeline);

        // 8. API Keys Health
        $todayRequests = \App\Models\AiRequestLog::whereDate('created_at', Carbon::today())
            ->select('api_key_id', DB::raw('count(*) as total'))
            ->groupBy('api_key_id')
            ->pluck('total', 'api_key_id');

        $quotaErrors = \App\Models\ApiLog::where(function($q) {
                $q->where('status_code', 429)->orWhere('message', 'like', '%quota%');
            })
            ->groupBy('api_key_id')
            ->select('api_key_id', DB::raw('count(*) as total'))
            ->get()
            ->pluck('total', 'api_key_id')
            ->toArray();

        $apiKeys = \App\Models\ApiKey::with(['vault', 'capabilitiesList'])
            ->whereHas('capabilitiesList', function ($q) {
                $q->whereIn('capability', [
                    \App\Models\ApiKey::CAPABILITY_SEARCH,
                    \App\Models\ApiKey::CAPABILITY_EMBEDDING,
                    \App\Models\ApiKey::CAPABILITY_QUERY_EMBEDDING
                ]);
            })
            ->get()
            ->map(function ($key) use ($todayRequests, $quotaErrors) {
                $blacklist = Cache::get('api_key_blacklist', []);
                $isBlacklisted = in_array($key->id, $blacklist);
                
                $isRateLimited = $key->last_error_at && str_contains(strtolower($key->last_error_message), 'rate limit') && now()->lt($key->last_error_at->addMinutes(1));
                
                // Labeling capabilities for clarity in the dashboard
                $caps = $key->capabilitiesList->pluck('capability')->toArray();
                $displayCaps = [];
                if (in_array(\App\Models\ApiKey::CAPABILITY_EMBEDDING, $caps)) $displayCaps[] = 'Indexação';
                if (in_array(\App\Models\ApiKey::CAPABILITY_SEARCH, $caps) || in_array(\App\Models\ApiKey::CAPABILITY_QUERY_EMBEDDING, $caps)) $displayCaps[] = 'Busca';

                $recentAiLogs = \App\Models\AiRequestLog::where('api_key_id', $key->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get()
                    ->groupBy(function($log) {
                        return $log->module . '_' . $log->created_at->format('Y-m-d H:i:s');
                    })
                    ->map(function($group) {
                        $first = $group->first();
                        return [
                            'type' => 'request',
                            'module' => $first->module,
                            'tokens' => $group->sum('tokens_used_total'),
                            'execution_time' => $group->avg('execution_time'),
                            'status' => 'success',
                            'created_at' => $first->created_at->toIso8601String(),
                            'count' => $group->count(),
                            'items' => $group->map(fn($log) => [
                                'prompt' => $log->prompt_text,
                                'response' => $log->response_text,
                                'tokens_in' => $log->tokens_used_input,
                                'tokens_out' => $log->tokens_used_output,
                                'tokens_total' => $log->tokens_used_total,
                            ])->values()->all()
                        ];
                    });

                $recentErrorLogs = \App\Models\ApiLog::where('api_key_id', $key->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn($log) => [
                        'type' => 'error',
                        'module' => 'System',
                        'tokens' => 0,
                        'execution_time' => 0,
                        'status' => $log->status_code == 429 ? 'quota_exceeded' : 'error',
                        'message' => $log->message,
                        'created_at' => $log->created_at->toIso8601String(),
                        'count' => 1,
                        'items' => []
                    ]);

                $recentLogs = $recentAiLogs->concat($recentErrorLogs)
                    ->sortByDesc('created_at')
                    ->values()
                    ->all();

                return [
                    'id' => $key->id,
                    'name' => $key->vault ? $key->vault->nickname : ($key->provider . ' (Direct)'),
                    'provider' => current(explode('_', $key->provider)), // 'openai', 'groq', 'azure' etc
                    'model' => $key->preferred_model ?? 'N/A',
                    'status' => $isBlacklisted ? 'blacklisted' : ($isRateLimited ? 'rate_limit' : $key->status),
                    'is_blacklisted' => $isBlacklisted,
                    'capabilities' => $displayCaps,
                    'rate_limit_ends_in' => $isRateLimited ? now()->diffInSeconds($key->last_error_at->addMinutes(1)) : null,
                    'total_requests' => $key->requests_count,
                    'requests_today' => $todayRequests[(string)$key->id] ?? $todayRequests[(int)$key->id] ?? 0,
                    'quota_exceeded_count' => $quotaErrors[(string)$key->id] ?? $quotaErrors[(int)$key->id] ?? 0,
                    'error_rate' => $key->requests_count > 0 ? 0 : 0, 
                    'recent_logs' => $recentLogs,
                ];
            });

        return response()->json([
            'overview' => [
                'mysql_published_questions' => $totalQuestions,
                'mysql_indexed_questions'   => $indexedQuestions,
                'mysql_total_vectors'       => $totalVectors,
                'mysql_total_subjects'      => $totalSubjects,
                'mysql_indexed_subjects'    => $indexedSubjects,
                'mysql_total_topics'        => $totalTopics,
                'mysql_indexed_topics'      => $indexedTopics,
            ],
            'qdrant' => [
                'status'           => $qdrantStatus,
                'questions_points' => $qdrantQuestions,
                'concepts_points'  => $qdrantConcepts,
                'index_version_status' => [
                    'expected'  => $currentPipeline,
                    'questions' => $questionsVersionCheck,
                    'concepts'  => $conceptsVersionCheck,
                ]
            ],
            'top_concepts' => \App\Models\Subject::whereNotNull('qdrant_indexed_at')
                ->withCount('questions')
                ->orderByDesc('questions_count')
                ->limit(20)
                ->get(['id', 'name'])
                ->map(fn($s) => ['name' => $s->name, 'count' => $s->questions_count, 'type' => 'subject'])
                ->concat(
                    \App\Models\Topic::whereNotNull('qdrant_indexed_at')
                        ->withCount('questions')
                        ->orderByDesc('questions_count')
                        ->limit(30)
                        ->get(['id', 'name'])
                        ->map(fn($t) => ['name' => $t->name, 'count' => $t->questions_count, 'type' => 'topic'])
                )
                ->sortByDesc('count')
                ->values(),
            'performance' => [
                'total_searches'      => $totalLogSearches,
                'l1_cache_hits'       => $l1CacheHits,
                'l2_cache_hits'       => $l2CacheHits,
                'total_cache_entries' => $totalCacheEntries,
            ],
            'jobs' => [
                'pending' => $pendingJobs,
                'failed'  => $failedJobs,
                'recent_failures' => $failedJobsDetails,
                'recent_successes' => $recentSuccesses,
                'waiting_list'    => app(\App\Services\AI\AIService::class)->getCongestionList()['items'] ?? [],
                'waiting_total'   => app(\App\Services\AI\AIService::class)->getCongestionList()['total'] ?? 0,
            ],
            'analytics' => [
                'total_ai_requests' => $totalAiRequests,
                'success_rate'      => $successRate,
                'top_prompts'       => $topPrompts,
                'chart_data'        => $chartData,
            ],
            'recent_searches' => $recentSearches,
            'api_keys' => $apiKeys,
            'search_cache' => \App\Models\AiSearchCache::orderBy('created_at', 'desc')->limit(10)->get(),
            'config' => [
                'vector_search_enabled'       => \App\Models\Configuration::get('xavier_vector_search_enabled', config('xavier.vector_search_enabled')),
                'concept_detection_threshold' => \App\Models\Configuration::get('xavier_concept_detection_threshold', config('xavier.embeddings.concept_detection_threshold')),
                'search_threshold'           => \App\Models\Configuration::get('xavier_search_threshold', config('xavier.embeddings.search_threshold')),
                'qdrant_candidate_limit'      => \App\Models\Configuration::get('xavier_qdrant_candidate_limit', config('xavier.search.qdrant_candidate_limit')),
                'final_result_limit'          => \App\Models\Configuration::get('xavier_final_result_limit', config('xavier.search.final_result_limit')),
                'rerank_weights'              => json_decode(\App\Models\Configuration::get('xavier_rerank_weights', json_encode(config('xavier.search.rerank_weights'))), true),
                'pipeline_version'            => config('xavier.embeddings.pipeline_version'),
                'search_cache_enabled'        => \App\Models\Configuration::get('xavier_search_cache_enabled', '1') === '1',
            ]
        ]);
    }

    /**
     * Update runtime configurations for the semantic engine.
     * Persists settings to the database configurations table.
     */
    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'vector_search_enabled'       => 'boolean',
            'concept_detection_threshold' => 'numeric|min:0',
            'search_threshold'           => 'numeric|min:0',
            'qdrant_candidate_limit'      => 'integer|min:10|max:300',
            'final_result_limit'          => 'integer|min:5|max:150',
            'rerank_weights'              => 'nullable|array',
            'rerank_weights.vector'       => 'numeric|min:0|max:1',
            'rerank_weights.popularity'   => 'numeric|min:0|max:1',
            'rerank_weights.quality'      => 'numeric|min:0|max:1',
            'rerank_weights.recency'      => 'numeric|min:0|max:1',
            'search_cache_enabled'        => 'boolean',
        ]);

        if ($request->has('vector_search_enabled')) {
            \App\Models\Configuration::set('xavier_vector_search_enabled', $request->boolean('vector_search_enabled') ? '1' : '0');
        }

        if ($request->has('concept_detection_threshold')) {
            \App\Models\Configuration::set('xavier_concept_detection_threshold', (string) $validated['concept_detection_threshold']);
        }

        if ($request->has('search_threshold')) {
            \App\Models\Configuration::set('xavier_search_threshold', (string) $validated['search_threshold']);
        }

        if ($request->has('qdrant_candidate_limit')) {
            \App\Models\Configuration::set('xavier_qdrant_candidate_limit', (string) $validated['qdrant_candidate_limit']);
        }

        if ($request->has('final_result_limit')) {
            \App\Models\Configuration::set('xavier_final_result_limit', (string) $validated['final_result_limit']);
        }

        if ($request->has('rerank_weights')) {
            \App\Models\Configuration::set('xavier_rerank_weights', json_encode($validated['rerank_weights']));
        }

        if ($request->has('search_cache_enabled')) {
            \App\Models\Configuration::set('xavier_search_cache_enabled', $request->boolean('search_cache_enabled') ? '1' : '0');
        }

        return response()->json(['message' => 'Configurações atualizadas no banco de dados com sucesso.']);
    }

    /**
     * Clear the AI triage queue by cancelling active batches and approving pending questions.
     */
    public function clearTriageQueue()
    {
        try {
            DB::beginTransaction();

            // 1. Cancel all active AI batches
            \App\Models\AiProcessingBatch::whereIn('status', ['processing', 'retrying'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            // 2. Clear Redis semaphores to allow new processes to start
            \Illuminate\Support\Facades\Redis::del('ai_triage');
            \Illuminate\Support\Facades\Redis::del('ai_triage_queue'); // Just in case

            DB::commit();

            return response()->json(['message' => 'Processamentos de triagem cancelados e semáforos liberados. As questões permanecem com seu status original.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erro ao limpar fila: ' . $e->getMessage()], 500);
        }
    }
}
