import { cva } from 'class-variance-authority';
import { X } from 'lucide-react';
import { cn } from '@/utils/cn';

const alertVariants = cva(
    'relative w-full rounded-lg border px-4 py-3 text-sm [&>svg]:size-4 [&>svg]:translate-y-0.5',
    {
        variants: {
            variant: {
                default: 'bg-card text-card-foreground',
                destructive: 'bg-destructive/10 text-destructive border-destructive/30',
                success: 'bg-success/10 text-success border-success/30',
                info: 'bg-info/10 text-info border-info/30',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export function Alert({ className, variant, message, onDismiss, children, ...props }) {
    const content = message || children;
    if (!content) return null;

    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        >
            <div className="flex items-center justify-between gap-2">
                <span>{content}</span>
                {onDismiss && (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="shrink-0 text-inherit opacity-60 hover:opacity-100 bg-transparent border-none cursor-pointer"
                        aria-label="Dismiss"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}

export function AlertTitle({ className, ...props }) {
    return (
        <div
            data-slot="alert-title"
            className={cn('min-h-4 font-medium tracking-tight', className)}
            {...props}
        />
    );
}

export function AlertDescription({ className, ...props }) {
    return (
        <div
            data-slot="alert-description"
            className={cn('text-sm [&_p]:leading-relaxed', className)}
            {...props}
        />
    );
}
