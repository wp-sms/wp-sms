import { useState } from 'preact/hooks';
import {
    authError,
    registrationToken,
    pendingVerifications,
    resetIdentifyFlow,
} from '../../signals/auth';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { PhoneVerifySection } from '../verification/PhoneVerifySection';
import { EmailVerifySection } from '../verification/EmailVerifySection';

function regHeaders() {
    return { 'X-Registration-Token': registrationToken.value };
}

export function RegisterVerifyStep() {
    const verifications = pendingVerifications.value;
    const hasPhone = verifications.some((v) => v.type === 'phone');
    const hasEmail = verifications.some((v) => v.type === 'email');

    const [phoneVerified, setPhoneVerified] = useState(false);

    if (!hasPhone || phoneVerified) {
        return (
            <div className="space-y-4 animate-fade-in">
                <Alert variant="success" message="Your phone has been verified!" />

                {hasEmail && (
                    <p className="text-sm text-muted-foreground text-center">
                        We've sent a verification link to your email. You can verify it anytime.
                    </p>
                )}

                <Button className="w-full" onClick={resetIdentifyFlow}>
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

            {hasEmail && (
                <EmailVerifySection headers={regHeaders()} className="border-t pt-4" />
            )}
        </div>
    );
}
