import { cn } from '@/utils/cn';

const VARIANT = {
    default: 'wsms-auth-btn--default',
    destructive: 'wsms-auth-btn--destructive',
    outline: 'wsms-auth-btn--outline',
    secondary: 'wsms-auth-btn--secondary',
    ghost: 'wsms-auth-btn--ghost',
    link: 'wsms-auth-btn--link',
};

const SIZE = {
    default: 'wsms-auth-btn--sz-default',
    xs: 'wsms-auth-btn--sz-xs',
    sm: 'wsms-auth-btn--sz-sm',
    lg: 'wsms-auth-btn--sz-lg',
    icon: 'wsms-auth-btn--sz-icon',
};

function buttonVariants({ variant = 'default', size = 'default', className } = {}) {
    return cn('wsms-auth-btn', VARIANT[variant], SIZE[size], className);
}

function Button({ className, variant, size, loading, children, disabled, ...props }) {
    return (
        <button
            data-slot="button"
            className={buttonVariants({ variant, size, className })}
            disabled={disabled || loading}
            {...props}
        >
            {loading && <span className="wsms-auth-spinner--inline" />}
            {children}
        </button>
    );
}

export { Button, buttonVariants };
