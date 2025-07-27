import { PanelLeftOpen } from 'lucide-react';
import { SettingsSidebar } from './sidebar';
import type { SettingsLayoutProps } from './types';
import { Button } from '@/components/ui/button';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Link } from 'react-router-dom';
import { useSidebarStore } from '@/stores/sidebar';

export const SettingsLayout: React.FC<SettingsLayoutProps> = ({ children }) => {
    const { isOpen } = useSidebarStore();

    console.log({ isOpen });

    return (
        <div className="wrap flex w-full min-h-screen">
            <SettingsSidebar />

            <div className="flex-1 bg-white">
                <header className="border-b border-b-border p-3 sticky top-8 bg-white z-10">
                    <div className="flex items-center gap-x-4">
                        <Button size="icon" variant="ghost" className="cursor-pointer">
                            <PanelLeftOpen className="text-foreground/85" size={22} />
                        </Button>

                        <div className="w-[1px] h-5 bg-border rotate-180" />

                        <Breadcrumb>
                            <BreadcrumbList className="!list-none !ml-1">
                                <BreadcrumbItem>
                                    <BreadcrumbLink asChild>
                                        <Link to="/">WP SMS</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>

                                <BreadcrumbSeparator />

                                <BreadcrumbItem>
                                    <BreadcrumbLink asChild>
                                        <Link to="/">Settings</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>

                                <BreadcrumbSeparator />

                                <BreadcrumbItem>
                                    <BreadcrumbLink asChild>
                                        <Link to="/">General</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </header>

                {children}
            </div>
        </div>
    );
};
