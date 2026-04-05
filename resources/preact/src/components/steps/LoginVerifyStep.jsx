import { useState, useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { api } from '../../api/client';
import {
    authError,
    challengeToken,
    pendingVerifications,
} from '../../signals/auth';
import { authConfig } from '../../signals/config';
import { handleAuthResponse, extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { PhoneVerifySection } from '../verification/PhoneVerifySection';
import { EmailVerifySection } from '../verification/EmailVerifySection';

function verifyHeaders() {
    return { 'X-Auth-Session': challengeToken.value };
}

export function LoginVerifyStep() {
    const verifications = pendingVerifications.value;
    const hasPhone = verifications.some((v) => v.type === 'phone');
    const hasEmail = verifications.some((v) => v.type === 'email');

    const config = authConfig.value;
    const emailIsOtp = config?.method_details?.email?.has_otp ?? false;

    const [phoneVerified, setPhoneVerified] = useState(false);
    const [emailVerified, setEmailVerified] = useState(false);

    const phoneDone = !hasPhone || phoneVerified;

    async function tryCompleteLogin() {
        authError.value = null;
        try {
            const res = await api.post('/auth/verification/complete', null, verifyHeaders());
            handleAuthResponse(res);
        } catch (err) {
            authError.value = extractError(err).message;
        }
    }

    // Complete login when all verifications are done.
    useEffect(() => {
        const emailDone = !hasEmail || emailVerified;
        if (phoneDone && emailDone && (phoneVerified || emailVerified)) {
            tryCompleteLogin();
        }
    }, [phoneVerified, emailVerified]); // eslint-disable-line react-hooks/exhaustive-deps

    // Poll for email verification status only in magic-link mode.
    // Only starts when email section is visible (phone done first).
    useEffect(() => {
        if (!phoneDone || !hasEmail || emailIsOtp) return;
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
                }
            } catch {
                // Polling failure is non-critical.
            }
        }, 5000);
        return () => { stopped = true; clearInterval(interval); };
    }, [phoneDone, hasEmail, emailIsOtp]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="wsms-auth-stack-6 wsms-auth-fade-in">
            <Alert
                variant="destructive"
                message={authError.value}
                onDismiss={() => (authError.value = null)}
                className="wsms-auth-mb-4"
            />

            {hasPhone && !phoneVerified && (
                <PhoneVerifySection
                    headers={verifyHeaders()}
                    onVerified={() => setPhoneVerified(true)}
                />
            )}

            {hasPhone && phoneVerified && (
                <Alert variant="success" message={__('Phone verified!', 'wp-sms')} />
            )}

            {phoneDone && hasEmail && !emailVerified && (
                <EmailVerifySection
                    headers={verifyHeaders()}
                    className={hasPhone ? 'wsms-auth-border-t' : ''}
                    onVerified={() => setEmailVerified(true)}
                />
            )}

            {hasEmail && emailVerified && (
                <Alert variant="success" message={__('Email verified!', 'wp-sms')} />
            )}
        </div>
    );
}
