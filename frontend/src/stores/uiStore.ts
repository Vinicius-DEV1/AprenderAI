import { create } from 'zustand';

interface UIState {
    sidebarCollapsed: boolean;
    setSidebarCollapsed: (value: boolean) => void;
    toggleSidebar: () => void;

    // AI Batch Modal Global State
    isBatchModalOpen: boolean;
    batchModalConfig: { pendingCount: number } | null;
    openBatchModal: (pendingCount?: number) => void;
    closeBatchModal: () => void;
}

export const useUIStore = create<UIState>((set) => ({
    sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    setSidebarCollapsed: (value) => {
        localStorage.setItem('sidebar_collapsed', value.toString());
        set({ sidebarCollapsed: value });
    },
    toggleSidebar: () => set((state) => {
        const newValue = !state.sidebarCollapsed;
        localStorage.setItem('sidebar_collapsed', newValue.toString());
        return { sidebarCollapsed: newValue };
    }),

    isBatchModalOpen: false,
    batchModalConfig: null,
    openBatchModal: (pendingCount = 0) => set({
        isBatchModalOpen: true,
        batchModalConfig: { pendingCount }
    }),
    closeBatchModal: () => set({
        isBatchModalOpen: false,
        // Mantemos o config para não quebrar animações de fechamento se necessário
    }),
}));
