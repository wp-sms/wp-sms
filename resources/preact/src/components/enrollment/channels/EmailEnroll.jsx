import { useEffect, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Mail } from 'lucide-react';
import { Button } from '../../ui/Button';
import { OtpInput } from '../../OtpInput';
import { Spinner } from '../../ui/Spinner';
import { Alert } from '../../ui/Alert';
import { useResendCooldown } from '../../../hooks/useResendCooldown';
import { wizardError, wizardLoading, enrollmentData } from '../../../signals/enrollment';
import { currentUser } from '../../../signals/auth';

export function EmailEnroll({ onEnroll, onVerify, onChangeMethod }) {
    const loading = wizardLoading.value;
    const error = wizardError.value;
    const data = enrollmentData.value;
    const [cooldown, setCooldown] = useResendCooldown(0);
    const sentRef = useRef(false);

    // Auto-send on mount
    useEffect(() => {
        if (!sentRef.current) {
            sentRef.current = true;
            onEnroll('email').then(() => setCooldown(60));
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const email = currentUser.value?.email || '';
    const maskedEmail = data?.masked_email || email.replace(/(.{2}).*(@)/, '$1***$2');

    async function handleResend() {
        wizardError.value = null;
        await onEnroll('email');
        setCooldown(60);
    }

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <Mail />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Email Verification', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {sprintf(__('Enter the code sent to %s', 'wp-sms'), maskedEmail)}
                </p>
            </div>

            {error && (
                <Alert variant="destructive" onDismiss={() => { wizardError.value = null; }}>
                    {error.message}
                    {error.code === 'challenge_failed' && onChangeMethod && (
                        <Button variant="link" size="sm" onClick={onChangeMethod} className="wsms-auth-mt-1">
                            {__('Try a different method', 'wp-sms')}
                        </Button>
                    )}
                </Alert>
            )}

            <OtpInput
                onComplete={(code) => onVerify('email', code)}
                disabled={loading || (error?.retryAfter > 0)}
                error={!!error}
                autoFocus
            />

            {loading && (
                <div className="wsms-auth-flex-center wsms-auth-text-sm wsms-auth-text-muted">
                    <Spinner /> {__('Verifying...', 'wp-sms')}
                </div>
            )}

            <div className="wsms-auth-center">
                {error?.retryAfter > 0 ? (
                    <span className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-wizard-cooldown">
                        {sprintf(__('Try again in %ds', 'wp-sms'), error.retryAfter)}
                    </span>
                ) : cooldown > 0 ? (
                    <span className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-wizard-cooldown">
                        {sprintf(__('Resend in %ds', 'wp-sms'), cooldown)}
                    </span>
                ) : (
                    <Button variant="link" size="sm" onClick={handleResend} disabled={loading}>
                        {__('Resend Code', 'wp-sms')}
                    </Button>
                )}
            </div>
        </div>
    );
}
