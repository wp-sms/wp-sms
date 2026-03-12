import { useState, useEffect } from 'preact/hooks';
import { Mail } from 'lucide-react';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    registrationToken,
    pendingVerifications,
    resetIdentifyFlow,
} from '../../signals/auth';
import { extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { OtpInput } from '../OtpInput';

function regHeaders() {
    return { 'X-Registration-Token': registrationToken.value };
}

export function RegisterVerifyStep() {
    const verifications = pendingVerifications.value;
    const hasPhone = verifications.some((v) => v.type === 'phone');
    const hasEmail = verifications.some((v) => v.type === 'email');

    const [phoneVerified, setPhoneVerified] = useState(false);
    const [emailResent, setEmailResent] = useState(false);
    const [phoneResendCooldown, setPhoneResendCooldown] = useState(60);
    const [emailResendCooldown, setEmailResendCooldown] = useState(0);

    // Phone resend cooldown timer.
    useEffect(() => {
        if (phoneResendCooldown <= 0) return;
        const timer = setTimeout(() => setPhoneResendCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [phoneResendCooldown]);

    // Email resend cooldown timer.
    useEffect(() => {
        if (emailResendCooldown <= 0) return;
        const timer = setTimeout(() => setEmailResendCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [emailResendCooldown]);

    async function handlePhoneVerify(code) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/register/verify-phone', { code }, regHeaders());
            if (res.success) {
                setPhoneVerified(true);
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    async function handleResendPhone() {
        if (phoneResendCooldown > 0) return;
        authError.value = null;

        try {
            await api.post('/auth/register/resend-phone', null, regHeaders());
            setPhoneResendCooldown(60);
        } catch (err) {
            authError.value = extractError(err);
        }
    }

    async function handleResendEmail() {
        if (emailResendCooldown > 0) return;
        authError.value = null;

        try {
            const res = await api.post('/auth/register/resend-email', null, regHeaders());
            if (res.success) {
                setEmailResent(true);
                setEmailResendCooldown(60);
            }
        } catch (err) {
            authError.value = extractError(err);
        }
    }

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

            {/* Phone verification section */}
            {hasPhone && !phoneVerified && (
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground text-center">
                        Enter the 6-digit code sent to your phone
                    </p>

                    <OtpInput onComplete={handlePhoneVerify} disabled={authLoading.value} />

                    <div className="flex justify-center">
                        <Button
                            variant="link"
                            type="button"
                            onClick={handleResendPhone}
                            disabled={phoneResendCooldown > 0}
                        >
                            {phoneResendCooldown > 0 ? `Resend in ${phoneResendCooldown}s` : 'Resend code'}
                        </Button>
                    </div>
                </div>
            )}

            {/* Email verification section */}
            {hasEmail && (
                <div className="space-y-3 border-t pt-4">
                    <div className="flex items-center gap-2 justify-center">
                        <Mail className="size-4 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Check your email for a verification link
                        </p>
                    </div>

                    {emailResent && (
                        <p className="text-xs text-center text-green-600">Verification email resent!</p>
                    )}

                    <div className="flex justify-center">
                        <Button
                            variant="link"
                            type="button"
                            onClick={handleResendEmail}
                            disabled={emailResendCooldown > 0}
                        >
                            {emailResendCooldown > 0 ? `Resend email in ${emailResendCooldown}s` : 'Resend verification email'}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
