/**
 * Target Pipeline Version
 * Used for compatibility checks between the UI and the Backend Semantic Engine.
 */
export const TARGET_PIPELINE_VERSION = 'v8.1.0';

/**
 * DashboardStats
 * Core interface representing all metrics and system health data 
 * returned by the Semantic Dashboard API.
 */
export interface DashboardStats {
    overview: {
        mysql_published_questions: number;
        mysql_indexed_questions: number;
        mysql_total_subjects: number;
        mysql_indexed_subjects: number;
        mysql_total_topics: number;
        mysql_indexed_topics: number;
    };
    qdrant: {
        status: string;
        collections: Array<{ name: string; count: number }>;
        questions_points: number;
        vectors_count: number;
        concepts_points: number;
        index_version_status?: {
            expected: string;
            questions: { status: string; expected: string; actual: string; message: string };
            concepts: { status: string; expected: string; actual: string; message: string };
        };
    };
    performance: {
        total_searches: number;
        l1_cache_hits: number;
        l2_cache_hits: number;
        total_cache_entries: number;
    };
    jobs: {
        pending: number;
        failed: number;
        recent_failures?: Array<{
            id: number;
            failed_at: string;
            payload: string;
            error_preview: string;
        }>;
        waiting_list?: Array<{
            job: string;
            id: string | number;
            timestamp: string;
            ago: string;
        }>;
        waiting_total?: number;
    };
    analytics: {
        total_ai_requests: number;
        success_rate: number;
        top_prompts: Array<{ prompt: string; total: number }>;
        chart_data: Array<{ date: string; count: number; success: number; failed: number }>;
    };
    recent_searches?: Array<{
        id: number;
        user_name: string;
        prompt: string;
        status: string;
        created_at: string;
        similarity_threshold: number;
        filters?: any;
        error?: string;
    }>;
    api_keys: Array<{
        id: number;
        name: string;
        provider: string;
        model: string;
        status: string;
        rate_limit_ends_in: number | null;
        total_requests: number;
        error_rate: number;
        capabilities?: string[];
    }>;
    search_cache?: Array<{
        id: number;
        prompt_text: string;
        prompt_hash: string;
        filters_result: any;
        concept_ids: number[];
        last_used_at: string | null;
        created_at: string;
        vector_preview: number[];
    }>;
    top_concepts?: Array<{ name: string; count: number; type?: 'concept' | 'subject' | 'topic' }>;
    config: {
        vector_search_enabled: boolean | string;
        concept_detection_threshold: number;
        qdrant_candidate_limit: number;
        final_result_limit: number;
        rerank_weights: Record<string, number>;
        pipeline_version?: string;
        search_cache_enabled: boolean;
    };
}

/**
 * ConfigState
 * Represents the mutable configuration for the Semantic Engine.
 */
export interface ConfigState {
    vector_search_enabled: boolean;
    concept_detection_threshold: number;
    rerank_weights: {
        vector: number;
        popularity: number;
        quality: number;
        recency: number;
    };
    search_cache_enabled: boolean;
}
