import { cn } from '@/utils/cn';

export function Label({ className, ...props }) {
    return (
        <label
            data-slot="label"
            className={cn(
                'flex items-center gap-2 text-sm leading-none font-medium select-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
