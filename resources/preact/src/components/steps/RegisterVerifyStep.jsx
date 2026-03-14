import { useState } from 'preact/hooks';
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
            <div className="space-y-4 animate-fade-in">
                <Alert variant="success" message="Verification complete!" />

                {hasEmail && !emailVerified && (
                    <p className="text-sm text-muted-foreground text-center">
                        We've sent a verification link to your email. You can verify it anytime.
                    </p>
                )}

                <Button className="w-full" onClick={handleComplete}>
                    Continue to sign in
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-6 animate-fade-in">
            <Alert
                variant="destructive"
                message={authError.value}
                onDismiss={() => (authError.value = null)}
                className="mb-4"
            />

            {hasPhone && !phoneVerified && (
                <PhoneVerifySection headers={regHeaders()} onVerified={() => setPhoneVerified(true)} />
            )}

            {phoneComplete && hasEmail && !emailVerified && (
                <EmailVerifySection headers={regHeaders()} onVerified={() => setEmailVerified(true)}
                    className={hasPhone ? 'border-t pt-4' : ''} />
            )}
        </div>
    );
}
