import { forwardRef } from 'preact/compat';
import { cn } from '@/utils/cn';

export const Input = forwardRef(function Input({ className, type, ...props }, ref) {
    return (
        <input
            ref={ref}
            type={type}
            data-slot="input"
            className={cn('wsms-auth-input', className)}
            {...props}
        />
    );
});
