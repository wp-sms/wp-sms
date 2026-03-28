import { useState } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import {
    authError,
    registrationToken,
    pendingVerifications,
    resetIdentifyFlow,
} from '../../signals/auth';
import { methodDetails } from '../../signals/config';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { PhoneVerifySection } from '../verification/PhoneVerifySection';
import { EmailVerifySection } from '../verification/EmailVerifySection';

function regHeaders() {
    return { 'X-Auth-Session': registrationToken.value };
}

export function RegisterVerifyStep({ onComplete }) {
    const verifications = pendingVerifications.value;
    const hasPhone = verifications.some((v) => v.type === 'phone');
    const hasEmail = verifications.some((v) => v.type === 'email');
    const emailDetails = methodDetails.value.email;
    const emailIsOtp = emailDetails?.has_otp ?? false;

    const [phoneVerified, setPhoneVerified] = useState(false);
    const [emailVerified, setEmailVerified] = useState(false);

    const handleComplete = onComplete || resetIdentifyFlow;
    const phoneComplete = !hasPhone || phoneVerified;
    const emailNeedsOtp = hasEmail && emailIsOtp && !emailVerified;
    const allDone = phoneComplete && !emailNeedsOtp;

    if (allDone) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <Alert variant="success" message={__('Verification complete!', 'wp-sms')} />

                {hasEmail && !emailVerified && (
                    <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">
                        {__('We\'ve sent a verification link to your email. You can verify it anytime.', 'wp-sms')}
                    </p>
                )}

                <Button className="wsms-auth-full" onClick={handleComplete}>
                    {__('Continue to sign in', 'wp-sms')}
                </Button>
            </div>
        );
    }

    return (
        <div className="wsms-auth-stack-6 wsms-auth-fade-in">
            <Alert
                variant="destructive"
                message={authError.value}
                onDismiss={() => (authError.value = null)}
                className="wsms-auth-mb-4"
            />

            {hasPhone && !phoneVerified && (
                <PhoneVerifySection headers={regHeaders()} onVerified={() => setPhoneVerified(true)} />
            )}

            {phoneComplete && hasEmail && !emailVerified && (
                <EmailVerifySection headers={regHeaders()} onVerified={() => setEmailVerified(true)}
                    className={hasPhone ? 'wsms-auth-border-t' : ''} />
            )}
        </div>
    );
}
