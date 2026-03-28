import { cn } from '@/utils/cn';

export function Skeleton({ className, circle, style, ...props }) {
    return (
        <div
            className={cn('wsms-auth-skeleton', circle && 'wsms-auth-skeleton--circle', className)}
            style={style}
            {...props}
        />
    );
}

export function AccountSkeleton() {
    return (
        <div className="wsms-auth-fade-in">
            <div className="wsms-auth-identity-header">
                <Skeleton circle style={{ width: '3.5rem', height: '3.5rem' }} />
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
                    <Skeleton style={{ height: '0.875rem', width: '50%' }} />
                    <Skeleton style={{ height: '0.75rem', width: '35%' }} />
                </div>
            </div>

            <div className="wsms-auth-status-grid">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="wsms-auth-skeleton-card">
                        <Skeleton circle style={{ width: '2.25rem', height: '2.25rem' }} />
                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
                            <Skeleton style={{ height: '0.625rem', width: '40%' }} />
                            <Skeleton style={{ height: '0.75rem', width: '65%' }} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function SecuritySkeleton() {
    return (
        <div className="wsms-auth-fade-in wsms-auth-stack-3">
            {[0, 1].map((i) => (
                <div key={i} className="wsms-auth-skeleton-card">
                    <Skeleton circle style={{ width: '1.25rem', height: '1.25rem' }} />
                    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                        <Skeleton style={{ height: '0.875rem', width: '45%' }} />
                        <Skeleton style={{ height: '0.625rem', width: '70%' }} />
                    </div>
                    <Skeleton style={{ height: '2rem', width: '4.5rem', borderRadius: 'calc(var(--radius) - 2px)' }} />
                </div>
            ))}
        </div>
    );
}

export function ProfileSkeleton() {
    return (
        <div className="wsms-auth-fade-in">
            <div className="wsms-auth-avatar-section">
                <Skeleton circle style={{ width: '3.5rem', height: '3.5rem' }} />
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
                    <Skeleton style={{ height: '0.75rem', width: '6rem' }} />
                    <Skeleton style={{ height: '2rem', width: '5rem', borderRadius: 'calc(var(--radius) - 2px)' }} />
                </div>
            </div>

            <div className="wsms-auth-profile-grid">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="wsms-auth-stack-2">
                        <Skeleton style={{ height: '0.75rem', width: '30%' }} />
                        <Skeleton style={{ height: '2.5rem', width: '100%', borderRadius: 'calc(var(--radius) - 2px)' }} />
                    </div>
                ))}
            </div>
        </div>
    );
}
