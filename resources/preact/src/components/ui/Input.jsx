import { forwardRef } from 'preact/compat';
import { cn } from '@/utils/cn';

const LTR_TYPES = new Set(['email', 'url', 'tel', 'password', 'number']);

export const Input = forwardRef(function Input({ className, type, dir, ...props }, ref) {
    const resolvedDir = dir ?? (LTR_TYPES.has(type) ? 'ltr' : undefined);

    return (
        <input
            ref={ref}
            type={type}
            dir={resolvedDir}
            data-slot="input"
            className={cn('wsms-auth-input', className)}
            {...props}
        />
    );
});
