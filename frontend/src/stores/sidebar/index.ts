import { create } from 'zustand';
import type { SidebarStoreType } from './typest';

export const useSidebarStore = create<SidebarStoreType>((set) => ({
  isOpen: true,
  toggleSidebar: () => set((state) => ({ isOpen: !state.isOpen })),
}));
