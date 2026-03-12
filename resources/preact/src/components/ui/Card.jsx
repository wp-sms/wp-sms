import { cn } from '@/utils/cn';

export function Card({ className, ...props }) {
    return (
        <div
            data-slot="card"
            className={cn(
                'flex flex-col gap-6 rounded-xl border bg-card py-6 text-card-foreground shadow-sm',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({ className, ...props }) {
    return (
        <div
            data-slot="card-header"
            className={cn('flex flex-col gap-1.5 px-6', className)}
            {...props}
        />
    );
}

export function CardTitle({ className, ...props }) {
    return (
        <div
            data-slot="card-title"
            className={cn('leading-none font-semibold', className)}
            {...props}
        />
    );
}

export function CardDescription({ className, ...props }) {
    return (
        <div
            data-slot="card-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export function CardContent({ className, ...props }) {
    return (
        <div
            data-slot="card-content"
            className={cn('px-6', className)}
            {...props}
        />
    );
}

export function CardFooter({ className, ...props }) {
    return (
        <div
            data-slot="card-footer"
            className={cn('flex items-center px-6', className)}
            {...props}
        />
    );
}
