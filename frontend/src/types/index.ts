export interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    plan_id: number | null;
    plan?: {
        id: number;
        name: string;
        price: number;
        simulations_limit: number;
        essays_limit: number;
    };
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    simulation_limit?: {
        total: number;
        remaining: number;
        used: number;
    };
    quotas?: {
        simulations: { limit: number; used: number };
        essays: { limit: number; used: number };
        daily_questions: { limit: number; used: number };
        ai_questions: { limit: number; used: number };
    };
    subscriptions?: {
        id: number;
        status: string;
        is_manual_grant?: boolean;
        [key: string]: any;
    }[];
}

export interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
}

export interface Alternative {
    id: number;
    label: string;
    content: string;
    image_path?: string;
    is_correct?: boolean;
}

export interface QuestionImage {
    id: number;
    image_url: string;
}

export interface Question {
    id: number;
    year?: number;
    organization?: string;
    institution?: string;
    role?: string;
    source: string;
    number?: string;
    subjects: { id: number; name: string }[];
    difficulty: 'easy' | 'medium' | 'hard';
    statement_html?: string;
    statement?: string;
    review_status?: string;
    triage_logs?: {
        id: number;
        status: string;
        issues_detected?: string[];
        quality_score?: number;
    }[];
    tipo_questao?: 'Objetiva' | 'Discursiva' | 'Redação' | string;
    type?: string;
    image_path?: string;
    discursive_answer?: any;
    explanation?: string;
    alternatives: Alternative[];
    images?: QuestionImage[];
    already_answered?: boolean;
    was_correct?: boolean;
    is_favorite?: boolean;
    has_notes?: boolean;
    notebook_ids?: number[];
}
