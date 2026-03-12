import { useState, useEffect } from 'preact/hooks';
import { api } from '../../api/client';
import {
    authError,
    challengeToken,
    pendingVerifications,
} from '../../signals/auth';
import { handleAuthResponse, extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { PhoneVerifySection } from '../verification/PhoneVerifySection';
import { EmailVerifySection } from '../verification/EmailVerifySection';

function verifyHeaders() {
    return { 'X-Verification-Token': challengeToken.value };
}

export function LoginVerifyStep() {
    const verifications = pendingVerifications.value;
    const hasPhone = verifications.some((v) => v.type === 'phone');
    const hasEmail = verifications.some((v) => v.type === 'email');

    const [phoneVerified, setPhoneVerified] = useState(false);
    const [emailVerified, setEmailVerified] = useState(false);

    async function tryCompleteLogin() {
        authError.value = null;
        try {
            const res = await api.post('/auth/verification/complete', null, verifyHeaders());
            handleAuthResponse(res);
        } catch (err) {
            authError.value = extractError(err);
        }
    }

    // Poll for email verification status.
    useEffect(() => {
        if (!hasEmail) return;
        let stopped = false;
        const interval = setInterval(async () => {
            if (stopped) return;
            try {
                const res = await api.get('/auth/register/status', verifyHeaders());
                const emailPending = res.pending_verifications?.some((v) => v.type === 'email');
                if (!emailPending && !stopped) {
                    stopped = true;
                    clearInterval(interval);
                    setEmailVerified(true);
                    tryCompleteLogin();
                }
            } catch {
                // Polling failure is non-critical.
            }
        }, 5000);
        return () => { stopped = true; clearInterval(interval); };
    }, [hasEmail]);

    return (
        <div className="space-y-6 animate-fade-in">
            <Alert
                variant="destructive"
                message={authError.value}
                onDismiss={() => (authError.value = null)}
                className="mb-4"
            />

            {hasPhone && !phoneVerified && (
                <PhoneVerifySection
                    headers={verifyHeaders()}
                    onVerified={() => { setPhoneVerified(true); tryCompleteLogin(); }}
                />
            )}

            {hasPhone && phoneVerified && (
                <Alert variant="success" message="Phone verified!" />
            )}

            {hasEmail && !emailVerified && (
                <EmailVerifySection
                    headers={verifyHeaders()}
                    className={hasPhone ? 'border-t pt-4' : ''}
                />
            )}

            {hasEmail && emailVerified && (
                <Alert variant="success" message="Email verified!" />
            )}
        </div>
    );
}
