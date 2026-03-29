import { useState, useEffect } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../api/client';
import { authError, authLoading, stopLoading, challengeToken, challengeMeta, pendingMfa, clearAuth } from '../signals/auth';
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
            authError.value = extractError(err).message;
        } finally {
            stopLoading();
        }
    }

    async function handleResend() {
        if (resendCooldown > 0) return;
        authError.value = null;

        try {
            await api.post('/auth/resend', { session_token: token });
            setResendCooldown(60);
        } catch (err) {
            authError.value = extractError(err).message;
        }
    }

    function handleBackupSubmit(e) {
        e.preventDefault();
        if (backupCode.trim()) handleVerify(backupCode.trim());
    }

    if (!token) return null;

    const hasMagicLink = challengeMeta.value?.has_magic_link;
    const subtitle = challengeMeta.value?.masked_identifier
        ? sprintf(__('Enter the code sent to %s', 'wp-sms'), challengeMeta.value.masked_identifier)
        : pendingMfa.value
            ? __('Enter your verification code to continue.', 'wp-sms')
            : undefined;

    return (
        <AuthLayout
            title={__('Verify Your Identity', 'wp-sms')}
            subtitle={subtitle}
            footer={<AuthLink href={authUrl('/login')} onClick={() => clearAuth()}>{__('Back to login', 'wp-sms')}</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />

            {hasMagicLink && (
                <Alert variant="default" message={__('We also sent a login link — check your inbox if you prefer to click instead.', 'wp-sms')} className="wsms-auth-mb-4" />
            )}

            {useBackup ? (
                <form onSubmit={handleBackupSubmit} className="wsms-auth-stack-4">
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-backup">{__('Backup Code', 'wp-sms')}</Label>
                        <Input
                            ref={backupRef}
                            id="wsms-backup"
                            type="text"
                            dir="ltr"
                            value={backupCode}
                            onInput={(e) => setBackupCode(e.target.value)}
                            placeholder={__('Enter backup code', 'wp-sms')}
                            disabled={authLoading.value}
                            autoComplete="one-time-code"
                        />
                    </div>
                    <Button className="wsms-auth-full" type="submit" disabled={authLoading.value || !backupCode.trim()}>
                        {authLoading.value ? __('Verifying\u2026', 'wp-sms') : __('Verify Backup Code', 'wp-sms')}
                    </Button>
                    <Button variant="link" type="button" className="wsms-auth-full" onClick={() => setUseBackup(false)}>
                        {__('Use OTP instead', 'wp-sms')}
                    </Button>
                </form>
            ) : (
                <div className="wsms-auth-stack-4">
                    <OtpInput autoFocus onComplete={handleVerify} disabled={authLoading.value} />

                    <div className="wsms-auth-flex-gap">
                        <Button
                            variant="link"
                            type="button"
                            onClick={handleResend}
                            disabled={resendCooldown > 0}
                        >
                            {resendCooldown > 0 ? sprintf(__('Resend in %ds', 'wp-sms'), resendCooldown) : __('Resend code', 'wp-sms')}
                        </Button>

                        {pendingMfa.value && (
                            <Button variant="link" type="button" onClick={() => setUseBackup(true)}>
                                {__('Use backup code', 'wp-sms')}
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AuthLayout>
    );
}
