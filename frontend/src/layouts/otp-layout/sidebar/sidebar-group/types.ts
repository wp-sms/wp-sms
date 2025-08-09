import type { ReactNode } from 'react';

export interface SidebarGroupProps {
    title: string;
    children: ReactNode;
    showTitle?: boolean;
}
