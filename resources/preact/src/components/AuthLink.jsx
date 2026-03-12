import { cn } from '@/utils/cn';

export function AuthLink({ className, ...props }) {
    return (
        <a
            className={cn('text-sm text-primary hover:underline no-underline', className)}
            {...props}
        />
    );
}
