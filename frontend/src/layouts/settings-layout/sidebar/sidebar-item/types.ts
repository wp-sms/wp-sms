import type { PropsWithChildren } from 'react';

export type SidebarItemProps = PropsWithChildren<{
    icon?: string;
    title: string;
    href?: string;
    onClick?: () => void;
    showTitle?: boolean;

    endContent?: React.ReactNode;
}>;
