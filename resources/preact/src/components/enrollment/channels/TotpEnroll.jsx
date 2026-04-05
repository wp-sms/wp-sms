import { useEffect, useRef } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { KeyRound } from 'lucide-react';
import { OtpInput } from '../../OtpInput';
import { Spinner } from '../../ui/Spinner';
import { Alert } from '../../ui/Alert';
import { wizardError, wizardLoading, enrollmentData } from '../../../signals/enrollment';

export function TotpEnroll({ onEnroll, onVerify }) {
    const loading = wizardLoading.value;
    const error = wizardError.value;
    const data = enrollmentData.value;
    const sentRef = useRef(false);

    // Auto-enroll on mount to get QR code
    useEffect(() => {
        if (!sentRef.current) {
            sentRef.current = true;
            onEnroll('totp');
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    if (!data?.qr_code_uri) {
        return (
            <div className="wsms-auth-flex-center" style={{ minHeight: '12rem' }}>
                <Spinner />
            </div>
        );
    }

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <KeyRound />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Authenticator App', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Scan this QR code with your authenticator app, then enter the 6-digit code to verify.', 'wp-sms')}
                </p>
            </div>

            <div className="wsms-auth-flex-center">
                <img
                    src={data.qr_code_uri}
                    alt={__('QR code for authenticator app setup. Use the manual key below if you cannot scan.', 'wp-sms')}
                    className="wsms-auth-qr-image"
                />
            </div>

            {data.secret && (
                <details className="wsms-auth-text-sm">
                    <summary className="wsms-auth-totp-summary">
                        {__("Can't scan? Enter this key manually", 'wp-sms')}
                    </summary>
                    <code className="wsms-auth-totp-secret" aria-label={__('Manual setup key for authenticator app', 'wp-sms')}>
                        {data.secret}
                    </code>
                </details>
            )}

            {error && (
                <Alert variant="destructive" onDismiss={() => { wizardError.value = null; }}>
                    {error.message}
                </Alert>
            )}

            <OtpInput
                onComplete={(code) => onVerify('totp', code)}
                disabled={loading}
                error={!!error}
                autoFocus
            />

            {loading && (
                <div className="wsms-auth-flex-center wsms-auth-text-sm wsms-auth-text-muted">
                    <Spinner /> {__('Verifying...', 'wp-sms')}
                </div>
            )}
        </div>
    );
}
