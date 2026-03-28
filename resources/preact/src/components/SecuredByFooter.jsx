import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@/utils/cn';
import { Logo } from '@/components/Logo';

export function SecuredByFooter({ className }) {
    return (
        <div className={cn('wsms-auth-secured-by', className)}>
            <Logo className="wsms-auth-secured-by__icon" />
            <span className="wsms-auth-secured-by__text">
                {sprintf(__('Secured by %s', 'wp-sms'), 'WSMS')}
            </span>
        </div>
    );
}
