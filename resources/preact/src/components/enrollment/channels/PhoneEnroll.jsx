import { useState } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Smartphone } from 'lucide-react';
import { Button } from '../../ui/Button';
import { Label } from '../../ui/Label';
import { PhoneInput } from '../../PhoneInput';
import { OtpInput } from '../../OtpInput';
import { Spinner } from '../../ui/Spinner';
import { Alert } from '../../ui/Alert';
import { useResendCooldown } from '../../../hooks/useResendCooldown';
import { wizardError, wizardLoading, enrollmentData } from '../../../signals/enrollment';

export function PhoneEnroll({ onEnroll, onVerify, onChangeMethod }) {
    const [phone, setPhone] = useState('');
    const data = enrollmentData.value;
    const verifying = !!data?.requires_verification;
    const loading = wizardLoading.value;
    const error = wizardError.value;
    const [cooldown, setCooldown] = useResendCooldown(0);

    async function handleSend() {
        const res = await onEnroll('phone', { phone });
        if (res) setCooldown(60);
    }

    async function handleResend() {
        wizardError.value = null;
        const res = await onEnroll('phone', { phone });
        if (res) setCooldown(60);
    }

    if (verifying) {
        const maskedPhone = data.masked_phone || phone.replace(/.(?=.{4})/g, '*');
        return (
            <div className="wsms-auth-stack-4">
                <div className="wsms-auth-flex-center">
                    <div className="wsms-auth-wizard-icon">
                        <Smartphone />
                    </div>
                </div>
                <div className="wsms-auth-center wsms-auth-stack-2">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                        {__('Verify Your Phone', 'wp-sms')}
                    </h2>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {sprintf(__('Enter the code sent to %s', 'wp-sms'), maskedPhone)}
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
                    onComplete={(code) => onVerify('phone', code)}
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

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <Smartphone />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Phone Verification', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Enter your phone number to receive verification codes via SMS.', 'wp-sms')}
                </p>
            </div>

            {error && (
                <Alert variant="destructive" onDismiss={() => { wizardError.value = null; }}>
                    {error.message}
                </Alert>
            )}

            <div className="wsms-auth-stack-2">
                <Label>{__('Phone Number', 'wp-sms')}</Label>
                <PhoneInput value={phone} onChange={setPhone} disabled={loading} />
            </div>

            <Button className="wsms-auth-full" onClick={handleSend} disabled={loading || !phone}>
                {loading ? (
                    <><Spinner /> {__('Sending...', 'wp-sms')}</>
                ) : (
                    __('Send Verification Code', 'wp-sms')
                )}
            </Button>
        </div>
    );
}
