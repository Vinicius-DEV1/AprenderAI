import { create } from 'zustand';

interface UIState {
    sidebarCollapsed: boolean;
    setSidebarCollapsed: (value: boolean) => void;
    toggleSidebar: () => void;

    // AI Batch Modal Global State
    isBatchModalOpen: boolean;
    batchModalConfig: { pendingCount: number } | null;
    dismissedBatches: string[];
    openBatchModal: (pendingCount?: number) => void;
    closeBatchModal: () => void;
    dismissBatch: (batchId: string) => void;

    // Global Support Chat State
    isSupportChatOpen: boolean;
    setSupportChatOpen: (value: boolean) => void;
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
    dismissedBatches: JSON.parse(localStorage.getItem('dismissed_batches') || '[]'),
    openBatchModal: (pendingCount = 0) => set({
        isBatchModalOpen: true,
        batchModalConfig: { pendingCount }
    }),
    closeBatchModal: () => set({
        isBatchModalOpen: false,
    }),
    dismissBatch: (batchId) => set((state) => {
        const newList = [...state.dismissedBatches, batchId];
        localStorage.setItem('dismissed_batches', JSON.stringify(newList));
        return { dismissedBatches: newList };
    }),

    isSupportChatOpen: false,
    setSupportChatOpen: (value) => set({ isSupportChatOpen: value }),
}));
