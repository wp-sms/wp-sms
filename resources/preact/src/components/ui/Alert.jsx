import { X } from 'lucide-react';
import { cn } from '@/utils/cn';

const VARIANT = {
    default: 'wsms-auth-alert--default',
    destructive: 'wsms-auth-alert--destructive',
    success: 'wsms-auth-alert--success',
    info: 'wsms-auth-alert--info',
};

export function Alert({ className, variant = 'default', message, onDismiss, children, ...props }) {
    const content = message || children;
    if (!content) return null;

    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn('wsms-auth-alert', VARIANT[variant], className)}
            {...props}
        >
            <div className="wsms-auth-alert__inner">
                <span>{content}</span>
                {onDismiss && (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="wsms-auth-alert__dismiss"
                        aria-label="Dismiss"
                    >
                        <X />
                    </button>
                )}
            </div>
        </div>
    );
}
