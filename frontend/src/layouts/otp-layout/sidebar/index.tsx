import clsx from 'clsx';
import {Shield } from 'lucide-react';

import { useSidebarStore } from '@/stores/sidebar';

import { SidebarGroup } from './sidebar-group';
import { SidebarItem } from './sidebar-item';

export const OtpSidebar: React.FC = () => {
    const { isOpen } = useSidebarStore();

    const otpMenuItems = [
        {
            title: 'Activities',
            href: 'activities',
            icon: 'Activity'
        },
        {
            title: 'Logs',
            href: 'logs',
            icon: 'FileText'
        },
        {
            title: 'Channels',
            href: 'channels',
            icon: 'MessageSquare'
        },
        {
            title: 'Brandings',
            href: 'brandings',
            icon: 'Palette'
        },
        {
            title: 'Settings',
            href: 'settings',
            icon: 'Settings'
        }
    ];

    return (
        <aside className={clsx('bg-gray-50 w-72 p-5 border-r border-gray-200 overflow-hidden !transition-all')}>
            <div className="flex flex-col gap-y-10 sticky transition-all z-10">
                {isOpen && (
                    <section className="flex items-center gap-x-2.5">
                        <div className="size-10 rounded-lg bg-primary flex items-center justify-center">
                            <Shield className="text-white" size={21} />
                        </div>

                        <div className="flex flex-col">
                            <span className="text-gray-900 font-medium">WP SMS</span>
                            <span className="text-gray-500">OTP Management</span>
                        </div>
                    </section>
                )}

                <section className="flex flex-col gap-y-5">
                    <SidebarGroup title="OTP Management" showTitle={!!isOpen}>
                        {otpMenuItems.map((item) => (
                            <SidebarItem
                                key={item.href}
                                showTitle={!!isOpen}
                                href={item.href}
                                icon={item.icon}
                                title={item.title}
                            />
                        ))}
                    </SidebarGroup>
                </section>
            </div>
        </aside>
    );
};
