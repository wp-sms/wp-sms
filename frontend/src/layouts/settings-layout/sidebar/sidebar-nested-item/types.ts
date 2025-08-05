export type SidebarNestedItemProps = {
    title: string;
    icon?: string;
    items: Record<
        string,
        {
            label: string;
            name: string;
            icon: string;
        }
    >;
    showTitle?: boolean;
};
