import { cn } from '@/utils/cn';

export function Separator({ className, orientation = 'horizontal', ...props }) {
    return (
        <div
            data-slot="separator"
            role="separator"
            aria-orientation={orientation}
            className={cn(
                'shrink-0 bg-border',
                orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px',
                className,
            )}
            {...props}
        />
    );
}
