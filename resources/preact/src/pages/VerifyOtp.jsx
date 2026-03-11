import { useState, useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { api } from '../api/client';
import { authError, authLoading, challengeToken, challengeMeta, pendingMfa, clearAuth } from '../signals/auth';
import { handleAuthResponse, extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';
import { OtpInput } from '../components/OtpInput';

export function VerifyOtp() {
    const { route } = useLocation();
    const [useBackup, setUseBackup] = useState(false);
    const [backupCode, setBackupCode] = useState('');
    const [resendCooldown, setResendCooldown] = useState(0);

    const token = challengeToken.value || pendingMfa.value?.challenge_token;

    useEffect(() => {
        if (!token) route('/login');
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
                challenge_token: token,
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
            await api.post('/auth/resend', { challenge_token: token });
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

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Verify Your Identity</h1>

            {challengeMeta.value?.masked_identifier && (
                <p class="wsms-subtitle">
                    Enter the code sent to <strong>{challengeMeta.value.masked_identifier}</strong>
                </p>
            )}

            {pendingMfa.value && !challengeMeta.value?.masked_identifier && (
                <p class="wsms-subtitle">Enter your verification code to continue.</p>
            )}

            <Alert type="error" message={authError.value} onDismiss={() => (authError.value = null)} />

            {useBackup ? (
                <form onSubmit={handleBackupSubmit} class="wsms-form">
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-backup">Backup Code</label>
                        <input
                            id="wsms-backup"
                            class="wsms-input"
                            type="text"
                            value={backupCode}
                            onInput={(e) => setBackupCode(e.target.value)}
                            placeholder="Enter backup code"
                            disabled={authLoading.value}
                            autoComplete="one-time-code"
                        />
                    </div>
                    <button class="wsms-btn wsms-btn--primary" type="submit" disabled={authLoading.value || !backupCode.trim()}>
                        {authLoading.value ? 'Verifying\u2026' : 'Verify Backup Code'}
                    </button>
                    <button type="button" class="wsms-btn wsms-btn--text" onClick={() => setUseBackup(false)}>
                        Use OTP instead
                    </button>
                </form>
            ) : (
                <div class="wsms-form">
                    <OtpInput onComplete={handleVerify} disabled={authLoading.value} />

                    <div class="wsms-otp-actions">
                        <button
                            type="button"
                            class="wsms-btn wsms-btn--text"
                            onClick={handleResend}
                            disabled={resendCooldown > 0}
                        >
                            {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend code'}
                        </button>

                        {pendingMfa.value && (
                            <button type="button" class="wsms-btn wsms-btn--text" onClick={() => setUseBackup(true)}>
                                Use backup code
                            </button>
                        )}
                    </div>
                </div>
            )}

            <div class="wsms-links">
                <a href={authUrl('/login')} class="wsms-link" onClick={() => clearAuth()}>
                    Back to login
                </a>
            </div>
        </div>
    );
}
