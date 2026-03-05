import { create } from 'zustand';
import { User } from '../types';

interface AuthStoreState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    setUser: (user: User | null) => void;
    setLoading: (isLoading: boolean) => void;
    logout: () => void;
}

export const useAuthStore = create<AuthStoreState>((set) => ({
    user: null,
    isAuthenticated: false,
    isLoading: true,
    setUser: (user) => set({ user, isAuthenticated: !!user, isLoading: false }),
    setLoading: (isLoading) => set({ isLoading }),
    logout: () => {
        // Clear React Query cache before logging out to prevent data leakage 
        // Example: showing previous user's favorite questions on a new account
        set({ user: null, isAuthenticated: false, isLoading: false });
    },
}));
