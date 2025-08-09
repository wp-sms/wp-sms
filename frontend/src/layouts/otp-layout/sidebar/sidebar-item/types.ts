import type { ReactNode } from 'react';

export interface SidebarItemProps {
    icon?: string;
    title: string;
    href?: string;
    onClick?: () => void;
    endContent?: ReactNode;
    showTitle?: boolean;
}
