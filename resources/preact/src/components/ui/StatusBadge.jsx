import { ShieldCheck, AlertTriangle } from 'lucide-react';
import { cn } from '@/utils/cn';

const variants = {
    verified: {
        className: 'bg-green-50 text-green-700 border-green-200',
        icon: ShieldCheck,
        label: 'Verified',
    },
    unverified: {
        className: 'bg-amber-50 text-amber-700 border-amber-200',
        icon: AlertTriangle,
        label: 'Not Verified',
    },
    'not-set': {
        className: 'bg-muted text-muted-foreground border-border',
        icon: null,
        label: 'Not set',
    },
};

export function StatusBadge({ variant = 'not-set', className }) {
    const v = variants[variant] || variants['not-set'];
    const Icon = v.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
                v.className,
                className,
            )}
        >
            {Icon && <Icon className="size-3" />}
            {v.label}
        </span>
    );
}
