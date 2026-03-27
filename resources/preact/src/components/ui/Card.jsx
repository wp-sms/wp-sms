import { cn } from '@/utils/cn';

export function Card({ className, ...props }) {
    return <div data-slot="card" className={cn('wsms-auth-card', className)} {...props} />;
}

export function CardHeader({ className, ...props }) {
    return <div data-slot="card-header" className={cn('wsms-auth-card__header', className)} {...props} />;
}

export function CardTitle({ className, ...props }) {
    return <div data-slot="card-title" className={cn('wsms-auth-card__title', className)} {...props} />;
}

export function CardDescription({ className, ...props }) {
    return <div data-slot="card-description" className={cn('wsms-auth-card__desc', className)} {...props} />;
}

export function CardContent({ className, ...props }) {
    return <div data-slot="card-content" className={cn('wsms-auth-card__content', className)} {...props} />;
}

export function CardFooter({ className, ...props }) {
    return <div data-slot="card-footer" className={cn('wsms-auth-card__footer', className)} {...props} />;
}
