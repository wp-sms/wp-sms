import { cn } from '@/utils/cn';

export function Separator({ className, orientation = 'horizontal', ...props }) {
    return (
        <div
            data-slot="separator"
            role="separator"
            aria-orientation={orientation}
            className={cn(
                'wsms-auth-separator',
                orientation === 'vertical' && 'wsms-auth-separator--vertical',
                className,
            )}
            {...props}
        />
    );
}
