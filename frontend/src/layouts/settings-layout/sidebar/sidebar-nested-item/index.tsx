import clsx from 'clsx';
import { useEffect, useState } from 'react';
import { SidebarItem } from '../sidebar-item';
import { ChevronRightIcon } from 'lucide-react';
import type { SidebarNestedItemProps } from './types';
import { Link, useLocation } from 'react-router-dom';

export const SidebarNestedItem: React.FC<SidebarNestedItemProps> = ({ title, icon, items, showTitle = true }) => {
    const [isOpen, setIsOpen] = useState(false);

    const location = useLocation();

    useEffect(() => {
        const shouldOpen = Object.entries(items ?? {}).some(([key, value]) => {
            return location.pathname === `/${value?.name}`;
        });

        setIsOpen(shouldOpen);
    }, [location.pathname]);

    return (
        <div>
            <SidebarItem
                title={title}
                showTitle={showTitle}
                icon={icon}
                onClick={() => setIsOpen((prev) => !prev)}
                endContent={
                    <ChevronRightIcon size={13} className={clsx('transition-transform', isOpen ? 'rotate-90' : '')} />
                }
            />

            <div className={clsx('grid', isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]')}>
                <div className="overflow-hidden ">
                    <div className="mt-3 ml-5 pl-3 border-l border-l-gray-300 flex flex-col gap-y-3 mb-3">
                        {Object.entries(items ?? {})?.map(([key, value]) => {
                            const isActive = location.pathname === `/${value?.name}`;

                            return (
                                <Link
                                    to={value?.name}
                                    key={`nested-item-${title}-${value?.name}`}
                                    className={clsx(
                                        ' hover:font-medium cursor-pointer block ',
                                        isActive ? '!text-primary' : 'text-gray-700 hover:text-gray-900',
                                        showTitle ? 'block' : 'hidden'
                                    )}
                                >
                                    {value?.label}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
};
