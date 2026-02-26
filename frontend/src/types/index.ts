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
}

export interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
}
