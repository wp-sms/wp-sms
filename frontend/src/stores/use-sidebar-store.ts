import { create } from 'zustand'

export type SidebarStoreType = {
  isOpen: boolean
  toggleSidebar: VoidFunction
}

export const useSidebarStore = create<SidebarStoreType>((set) => ({
  isOpen: true,
  toggleSidebar: () => set((state) => ({ isOpen: !state.isOpen })),
}))
