import { create } from 'zustand';

interface UIState {
    sidebarCollapsed: boolean;
    setSidebarCollapsed: (value: boolean) => void;
    toggleSidebar: () => void;
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
}));
