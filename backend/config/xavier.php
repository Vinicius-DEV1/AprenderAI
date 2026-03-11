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
        // Named vector dimensions (Gemini embedding-001 = 3072, text-embedding-004 = 768)
        'vector_size' => env('QDRANT_VECTOR_SIZE', 768),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Pipeline
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        // Queue name for all indexing jobs
        'queue' => env('EMBEDDINGS_QUEUE', 'embeddings'),

        // Pipeline version tag written to question_vectors.pipeline_version
        'pipeline_version' => 'v3_structured',

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
        // Qdrant returns this many candidates before ReRankService filters to top-20
        'qdrant_candidate_limit' => 50,

        // Final results returned to the user
        'final_result_limit' => 20,

        // ReRankService score weights (must sum to 1.0)
        'rerank_weights' => [
            'vector'     => 0.60,
            'popularity' => 0.15,
            'quality'    => 0.15,
            'recency'    => 0.10,
        ],
    ],
];
