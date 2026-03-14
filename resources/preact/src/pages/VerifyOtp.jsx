import { useState, useEffect } from 'preact/hooks';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../api/client';
import { authError, authLoading, challengeToken, challengeMeta, pendingMfa, clearAuth } from '../signals/auth';
import { handleAuthResponse, extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
import { OtpInput } from '../components/OtpInput';

export function VerifyOtp() {
    const { route } = useLocation();
    const [useBackup, setUseBackup] = useState(false);
    const [backupCode, setBackupCode] = useState('');
    const backupRef = useAutoFocus(useBackup);
    const [resendCooldown, setResendCooldown] = useState(0);

    const token = challengeToken.value || pendingMfa.value?.session_token;

    useEffect(() => {
        if (!token) route(authUrl('/login'));
    }, [token, route]);

    useEffect(() => {
        if (resendCooldown <= 0) return;
        const timer = setTimeout(() => setResendCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [resendCooldown]);

    async function handleVerify(code) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/verify', {
                session_token: token,
                code,
            });
            handleAuthResponse(res, route);
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    async function handleResend() {
        if (resendCooldown > 0) return;
        authError.value = null;

        try {
            await api.post('/auth/resend', { session_token: token });
            setResendCooldown(60);
        } catch (err) {
            authError.value = extractError(err);
        }
    }

    function handleBackupSubmit(e) {
        e.preventDefault();
        if (backupCode.trim()) handleVerify(backupCode.trim());
    }

    if (!token) return null;

    const hasMagicLink = challengeMeta.value?.has_magic_link;
    const subtitle = challengeMeta.value?.masked_identifier
        ? `Enter the code sent to ${challengeMeta.value.masked_identifier}`
        : pendingMfa.value
            ? 'Enter your verification code to continue.'
            : undefined;

    return (
        <AuthLayout
            title="Verify Your Identity"
            subtitle={subtitle}
            footer={<AuthLink href={authUrl('/login')} onClick={() => clearAuth()}>Back to login</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            {hasMagicLink && (
                <Alert variant="default" message="We also sent a login link — check your inbox if you prefer to click instead." className="mb-4" />
            )}

            {useBackup ? (
                <form onSubmit={handleBackupSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label for="wsms-backup">Backup Code</Label>
                        <Input
                            ref={backupRef}
                            id="wsms-backup"
                            type="text"
                            value={backupCode}
                            onInput={(e) => setBackupCode(e.target.value)}
                            placeholder="Enter backup code"
                            disabled={authLoading.value}
                            autoComplete="one-time-code"
                        />
                    </div>
                    <Button className="w-full" type="submit" disabled={authLoading.value || !backupCode.trim()}>
                        {authLoading.value ? 'Verifying\u2026' : 'Verify Backup Code'}
                    </Button>
                    <Button variant="link" type="button" className="w-full" onClick={() => setUseBackup(false)}>
                        Use OTP instead
                    </Button>
                </form>
            ) : (
                <div className="space-y-4">
                    <OtpInput autoFocus onComplete={handleVerify} disabled={authLoading.value} />

                    <div className="flex justify-center gap-4">
                        <Button
                            variant="link"
                            type="button"
                            onClick={handleResend}
                            disabled={resendCooldown > 0}
                        >
                            {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend code'}
                        </Button>

                        {pendingMfa.value && (
                            <Button variant="link" type="button" onClick={() => setUseBackup(true)}>
                                Use backup code
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AuthLayout>
    );
}
