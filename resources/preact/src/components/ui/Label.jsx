import { cn } from '@/utils/cn';

export function Label({ className, ...props }) {
    return (
        <label
            data-slot="label"
            className={cn('wsms-auth-label', className)}
            {...props}
        />
    );
}
