import { useEffect, useRef } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { Button } from '../../ui/Button';
import { Spinner } from '../../ui/Spinner';
import { Alert } from '../../ui/Alert';
import { wizardError, wizardLoading, enrollmentData } from '../../../signals/enrollment';
import { refreshUser, enrolledFactors } from '../../../signals/user';
import { api } from '../../../api/client';
import { currentUser } from '../../../signals/auth';

export function DeepLinkEnroll({ channelId, icon: Icon, brandColor, label, onEnroll, onEnrollmentComplete }) {
    const loading = wizardLoading.value;
    const error = wizardError.value;
    const data = enrollmentData.value;
    const sentRef = useRef(false);
    const pollRef = useRef(null);

    useEffect(() => {
        if (!sentRef.current) {
            sentRef.current = true;
            onEnroll(channelId);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Poll for enrollment completion — only update signal when enrollment detected
    useEffect(() => {
        if (!data?.deep_link) return;

        pollRef.current = setInterval(async () => {
            try {
                const res = await api.get('/auth/me');
                const factors = res.user?.enrolled_factors ?? [];
                if (factors.some((f) => f.channel_id === channelId)) {
                    currentUser.value = res.user;
                    clearInterval(pollRef.current);
                    pollRef.current = null;
                    onEnrollmentComplete();
                }
            } catch { /* ignore poll errors */ }
        }, 3000);

        return () => {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
        };
    }, [data?.deep_link, channelId, onEnrollmentComplete]);

    function handleRegenerate() {
        wizardError.value = null;
        enrollmentData.value = null;
        sentRef.current = false;
        onEnroll(channelId);
    }

    if (!data?.deep_link && !error) {
        return (
            <div className="wsms-auth-flex-center" style={{ minHeight: '12rem' }}>
                <Spinner />
            </div>
        );
    }

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon" style={{ background: `color-mix(in srgb, ${brandColor} 10%, transparent)`, color: brandColor }}>
                    <Icon />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">{label}</h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Click the button below to open the app and link your account.', 'wp-sms')}
                </p>
            </div>

            {error && (
                <Alert variant="destructive" onDismiss={() => { wizardError.value = null; }}>
                    {error.message}
                </Alert>
            )}

            {data?.deep_link && (
                <>
                    <a
                        href={data.deep_link}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="wsms-auth-btn wsms-auth-btn--default wsms-auth-full"
                        style={{ background: brandColor, textDecoration: 'none', justifyContent: 'center' }}
                    >
                        <Icon size={16} />
                        {label}
                    </a>

                    <div className="wsms-auth-wizard-waiting-box">
                        <Spinner />
                        <span className="wsms-auth-text-sm wsms-auth-text-muted">
                            {__('Waiting for confirmation...', 'wp-sms')}
                        </span>
                    </div>

                    <div className="wsms-auth-center">
                        <Button variant="link" size="sm" onClick={handleRegenerate} disabled={loading}>
                            {__('Generate New Link', 'wp-sms')}
                        </Button>
                    </div>
                </>
            )}
        </div>
    );
}
