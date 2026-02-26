import { create } from 'zustand';

interface ConfigState {
    appName: string;
    aiName: string;
    appVersion: string;
    googleLoginEnabled: boolean;
    features: {
        essays: boolean;
        simulations: boolean;
        studyPlan: boolean;
        questionBank: boolean;
    };
    plans: any[];
    isLoaded: boolean;
    setConfig: (data: any) => void;
}

export const useConfigStore = create<ConfigState>((set) => ({
    // Defaults are empty — the real values come from /api/v1/config (DB).
    // Never hardcode brand names here; the API is the single source of truth.
    appName: '',
    aiName: '',
    appVersion: '1.0.0',
    googleLoginEnabled: false,
    features: {
        essays: false,
        simulations: false,
        studyPlan: false,
        questionBank: false,
    },
    plans: [],
    isLoaded: false,
    setConfig: (data) => set({
        appName: data.app_name ?? '',
        aiName: data.ai_name ?? '',
        appVersion: data.app_version ?? '1.0.0',
        googleLoginEnabled: !!data.google_login_enabled,
        features: {
            essays: !!data.features?.essays,
            simulations: !!data.features?.simulations,
            studyPlan: !!data.features?.study_plan,
            questionBank: !!data.features?.question_bank,
        },
        plans: data.plans ?? [],
        isLoaded: true,
    }),
}));
