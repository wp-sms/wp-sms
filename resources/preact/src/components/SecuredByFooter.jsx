import { cn } from '@/utils/cn';
import { Logo } from '@/components/Logo';

export function SecuredByFooter({ className }) {
    return (
        <div className={cn('wsms-auth-secured-by', className)}>
            <Logo className="wsms-auth-secured-by__icon" />
            <span className="wsms-auth-secured-by__text">
                Secured by <span className="wsms-auth-secured-by__brand">WSMS</span>
            </span>
        </div>
    );
}
