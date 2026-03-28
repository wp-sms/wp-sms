import { __ } from '@wordpress/i18n';
import { cn } from '@/utils/cn';

export function Spinner({ className, ...props }) {
    return (
        <div
            className={cn('wsms-auth-spinner', className)}
            role="status"
            aria-label={__('Loading', 'wp-sms')}
            {...props}
        />
    );
}
