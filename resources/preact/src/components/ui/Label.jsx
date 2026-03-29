import { cn } from '@/utils/cn';

export function Label({ className, ...props }) {
    return (
        // eslint-disable-next-line jsx-a11y/label-has-associated-control -- receives htmlFor via props spread
        <label
            data-slot="label"
            className={cn('wsms-auth-label', className)}
            {...props}
        />
    );
}
