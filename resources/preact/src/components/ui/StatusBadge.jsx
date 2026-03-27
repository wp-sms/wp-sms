import { ShieldCheck, AlertTriangle } from 'lucide-react';
import { cn } from '@/utils/cn';

const variants = {
    verified: {
        className: 'wsms-auth-badge--verified',
        icon: ShieldCheck,
        label: 'Verified',
    },
    unverified: {
        className: 'wsms-auth-badge--unverified',
        icon: AlertTriangle,
        label: 'Not Verified',
    },
    'not-set': {
        className: 'wsms-auth-badge--not-set',
        icon: null,
        label: 'Not set',
    },
};

export function StatusBadge({ variant = 'not-set', className }) {
    const v = variants[variant] || variants['not-set'];
    const Icon = v.icon;

    return (
        <span className={cn('wsms-auth-badge', v.className, className)}>
            {Icon && <Icon />}
            {v.label}
        </span>
    );
}
