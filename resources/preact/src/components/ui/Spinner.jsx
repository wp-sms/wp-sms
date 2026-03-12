import { cn } from '@/utils/cn';

export function Spinner({ className, ...props }) {
    return (
        <div
            className={cn(
                'inline-block size-6 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary',
                className,
            )}
            role="status"
            aria-label="Loading"
            {...props}
        />
    );
}
