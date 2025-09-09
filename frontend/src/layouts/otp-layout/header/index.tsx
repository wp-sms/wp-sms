import { PanelLeftOpen } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';

export const OtpHeader: React.FC = () => {
    const location = useLocation();
    const currentPage = location.pathname.replace('/', '') || 'activities';
    
    // Map page names to display names
    const getPageDisplayName = (page: string) => {
        const pageMap: Record<string, string> = {
            'activities': 'Activities',
            'logs': 'Logs',
            'channels': 'Channels',
            'brandings': 'Brandings',
            'settings': 'Settings'
        };
        return pageMap[page] || 'Activities';
    };
    return (
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
                                <Link to="/">OTP Management</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>

                        <BreadcrumbSeparator />

                        <BreadcrumbItem>
                            <BreadcrumbLink asChild>
                                <Link to={`/${currentPage}`}>{getPageDisplayName(currentPage)}</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
            </div>
        </header>
    );
};
