<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Xavier Semantic Search Feature Flag
    |--------------------------------------------------------------------------
    | When false: system uses the legacy SQL+LLM pipeline (InterpretSearchPromptJob)
    | When true:  system uses the new Qdrant vector search pipeline
    */
    'vector_search_enabled' => env('VECTOR_SEARCH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Qdrant Configuration
    |--------------------------------------------------------------------------
    */
    'qdrant' => [
        'host'       => env('QDRANT_HOST', 'localhost'),
        'port'       => env('QDRANT_PORT', 6333),
        'api_key'    => env('QDRANT_API_KEY', null),
        'timeout'    => env('QDRANT_TIMEOUT', 10),
        'collections' => [
            'questions' => 'questions_vectors',
            'concepts'  => 'concepts_vectors',
        ],
        // Named vector dimensions — gemini-embedding-001 = 3072 dims
        'vector_size' => env('QDRANT_VECTOR_SIZE', 3072),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Pipeline
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        // Default queue for background indexing tasks (low priority)
        'queue' => env('EMBEDDINGS_QUEUE', 'low'),

        // Queue for bulk processing (triage, classification, etc.)
        'batch_queue' => env('AI_BATCH_QUEUE', 'ai_triage'),

        // Pipeline version tag written to question_vectors.pipeline_version
        // Bump this string to force re-indexing of all questions on next job run.
        'pipeline_version' => env('PIPELINE_VERSION', 'v8.1.0'),

        // Minimum cosine similarity score to accept a concept match
        'concept_detection_threshold' => env('CONCEPT_DETECTION_THRESHOLD', 0.45),

        // Max expanded concepts passed to Qdrant filter (avoid query dilution)
        'max_expanded_concepts' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hybrid Search
    |--------------------------------------------------------------------------
    */
    'search' => [
        // Qdrant returns this many candidates before ReRankService filters to top-100
        'qdrant_candidate_limit' => 200,

        // Final results returned to the user
        'final_result_limit' => 100,

        // ReRankService score weights (must sum to 1.0)
        'rerank_weights' => [
            'vector'     => 0.60,
            'popularity' => 0.15,
            'quality'    => 0.15,
            'recency'    => 0.10,

            // Xavier 2.0: Pedagogical & User Context Boosts
            'user_profile' => [
                'proficiency_boost' => 0.25, // For Weak Themes
                'intent_boost'      => 0.30, // For Subject/Organization Match
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Limits (Worker Unification Semaphores)
    |--------------------------------------------------------------------------
    | These values control how many concurrent executions of each job type
    | are allowed cluster-wide, enforced via Redis distributed semaphores.
    | Adjustable at runtime via .env — no rebuild needed.
    */
    'concurrency' => [
        // Max simultaneous AI embedding generation calls (protects Gemini API quota)
        'max_embeddings' => (int) env('XAVIER_MAX_CONCURRENT_EMBEDDINGS', 5),

        // Max simultaneous AI triage/classification batches
        'max_triage'     => (int) env('XAVIER_MAX_CONCURRENT_TRIAGE', 5),

        // Max simultaneous ZIP import orchestrations (sequential-safe)
        'max_import'     => (int) env('XAVIER_MAX_CONCURRENT_IMPORT', 1),
    ],
];
