import { __ } from '@wordpress/i18n';
import { ShieldCheck, AlertTriangle } from 'lucide-react';
import { cn } from '@/utils/cn';

const VARIANTS = {
    verified: {
        className: 'wsms-auth-badge--verified',
        icon: ShieldCheck,
        label: __('Verified', 'wp-sms'),
    },
    unverified: {
        className: 'wsms-auth-badge--unverified',
        icon: AlertTriangle,
        label: __('Not Verified', 'wp-sms'),
    },
    'not-set': {
        className: 'wsms-auth-badge--not-set',
        icon: null,
        label: __('Not set', 'wp-sms'),
    },
};

export function StatusBadge({ variant = 'not-set', className }) {
    const variants = VARIANTS;
    const v = variants[variant] || variants['not-set'];
    const Icon = v.icon;

    return (
        <span className={cn('wsms-auth-badge', v.className, className)}>
            {Icon && <Icon />}
            {v.label}
        </span>
    );
}
