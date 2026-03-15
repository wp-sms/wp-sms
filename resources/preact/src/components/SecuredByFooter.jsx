import { Logo } from '@/components/Logo';

export function SecuredByFooter({ className = '' }) {
    return (
        <div className={`flex items-center justify-center gap-1.5 ${className}`}>
            <Logo className="size-3.5 text-muted-foreground/30" />
            <span className="text-xs text-muted-foreground/50">
                Secured by <span className="font-medium text-muted-foreground/70">WSMS</span>
            </span>
        </div>
    );
}
