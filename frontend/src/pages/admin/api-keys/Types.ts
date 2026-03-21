export interface VaultKey {
    id: number;
    nickname: string;
    provider: string;
    is_valid: boolean;
    created_at: string;
}

export interface ApiKey {
    id: number;
    provider: string;
    effective_provider: string;
    preferred_model: string | null;
    status: 'online' | 'offline' | 'quota_exceeded';
    last_error_message: string | null;
    vault?: VaultKey;
    pivot: {
        id: number;
        capability: string;
        priority: number;
    };
}

export interface AiLog {
    id: number;
    user: { name: string } | null;
    provider: string;
    model: string;
    prompt_text: string;
    response_text: string;
    tokens_used_input: number;
    tokens_used_output: number;
    tokens_used_total: number;
    execution_time: number;
    estimated_cost: number;
    module: string;
    created_at: string;
    apiKey?: {
        vault?: {
            nickname: string;
        }
    };
}

export interface AiRanking {
    user_id: number;
    user: { name: string; email: string } | null;
    total_tokens: number;
    total_cost: number;
    request_count: number;
}

export interface AnalyticsDaily {
    date: string;
    provider: string;
    model?: string;
    nickname?: string;
    requests: number;
    cost: number;
    last_request_at?: string;
}

export interface AnalyticsModule {
    module: string;
    provider: string;
    requests: number;
    cost: number;
}

export const capabilityMeta: Record<string, { icon: string, description: string }> = {
    chat_tutor: { icon: '💬', description: 'Responde dúvidas dos alunos sobre questões resolvidas.' },
    questions: { icon: '✍️', description: 'Gera questões inéditas, explicações e gabaritos comentados.' },
    triage: { icon: '⚙️', description: 'Classifica e modera questões durante o processamento em lote.' },
    search: { icon: '🔍', description: 'Interpreta buscas em linguagem natural na barra de pesquisa.' }
};
