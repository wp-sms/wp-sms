import { useState, useEffect } from 'preact/hooks';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    pendingMfa,
    challengeToken,
    challengeMeta,
    selectedMethod as selectedAuthMethod,
} from '../../signals/auth';
import { trustedDevicesConfig } from '../../signals/config';
import { handleAuthResponse, extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';
import { OtpInput } from '../OtpInput';

function formatTtl(seconds) {
    const days = Math.round((seconds || 2592000) / 86400);
    return `${days} day${days !== 1 ? 's' : ''}`;
}

function TrustDeviceCheckbox({ id, checked, onChange, disabled, ttl }) {
    return (
        <div className="flex items-center gap-2 justify-center">
            <input type="checkbox" id={id} checked={checked}
                onChange={onChange}
                disabled={disabled}
                className="h-4 w-4 rounded border-input text-primary focus:ring-primary" />
            <label for={id} className="text-sm text-muted-foreground cursor-pointer select-none">
                Remember this device
                <span className="block text-xs text-muted-foreground/70">
                    Skip verification for {formatTtl(ttl)}
                </span>
            </label>
        </div>
    );
}

export function MfaStep() {
    const { route } = useLocation();
    const mfa = pendingMfa.value;
    const token = mfa?.session_token;
    const factors = mfa?.available_factors || [];

    const [activeFactor, setActiveFactor] = useState(null);
    const [challengeSent, setChallengeSent] = useState(false);
    const [useBackup, setUseBackup] = useState(false);
    const [backupCode, setBackupCode] = useState('');
    const [showFactorPicker, setShowFactorPicker] = useState(false);
    const [resendCooldown, setResendCooldown] = useState(0);
    const [trustDevice, setTrustDevice] = useState(false);
    const backupRef = useAutoFocus(useBackup);

    const trustedDevices = trustedDevicesConfig.value;
    const showTrustCheckbox = trustedDevices?.enabled;

    // Auto-select the best MFA factor and send challenge on mount.
    useEffect(() => {
        if (!token || factors.length === 0) return;

        // Pick smart default: prefer a factor from a different channel than primary.
        const primaryMethod = selectedAuthMethod.value || 'password';
        const primaryChannel = primaryMethod.startsWith('phone') ? 'phone' : primaryMethod.startsWith('email') ? 'email' : 'password';

        let defaultFactor = factors.find((f) => f.channel_id !== primaryChannel && f.channel_id !== 'backup_codes');
        if (!defaultFactor) defaultFactor = factors[0];

        setActiveFactor(defaultFactor.channel_id);
        sendMfaChallenge(defaultFactor.channel_id);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Resend cooldown timer.
    useEffect(() => {
        if (resendCooldown <= 0) return;
        const timer = setTimeout(() => setResendCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [resendCooldown]);

    if (!token) return null;

    async function sendMfaChallenge(channelId) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/mfa/send', {
                session_token: token,
                channel_id: channelId,
            });

            if (res.session_token) {
                challengeToken.value = res.session_token;
                challengeMeta.value = res.meta || null;
            }
            setChallengeSent(true);
            setResendCooldown(60);
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    async function handleVerify(code) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/mfa/verify', {
                session_token: token,
                code,
                channel_id: activeFactor,
                ...(trustDevice && { trust_device: true }),
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

    function switchFactor(channelId) {
        setActiveFactor(channelId);
        setShowFactorPicker(false);
        setChallengeSent(false);
        setUseBackup(false);
        sendMfaChallenge(channelId);
    }

    const subtitle = challengeMeta.value?.requires_delivery === false
        ? 'Enter the code from your authenticator app'
        : challengeMeta.value?.masked_identifier
            ? `Enter the code sent to ${challengeMeta.value.masked_identifier}`
            : 'Enter your verification code to continue.';

    // Factor picker overlay.
    if (showFactorPicker) {
        return (
            <div className="space-y-4 animate-fade-in">
                <p className="text-sm text-muted-foreground text-center">Choose a verification method</p>
                {factors.map((f) => (
                    <Button
                        key={f.channel_id}
                        variant="outline"
                        className="w-full"
                        onClick={() => switchFactor(f.channel_id)}
                    >
                        {f.name}
                    </Button>
                ))}
                <Button variant="link" className="w-full" onClick={() => setShowFactorPicker(false)}>
                    Cancel
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4 animate-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            <p className="text-sm text-muted-foreground text-center">{subtitle}</p>

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
                    {showTrustCheckbox && (
                        <TrustDeviceCheckbox id="wsms-trust-device-backup" checked={trustDevice}
                            onChange={(e) => setTrustDevice(e.target.checked)}
                            disabled={authLoading.value} ttl={trustedDevices?.ttl} />
                    )}
                    <Button className="w-full" type="submit" disabled={authLoading.value || !backupCode.trim()}>
                        {authLoading.value ? 'Verifying...' : 'Verify Backup Code'}
                    </Button>
                    <Button variant="link" type="button" className="w-full" onClick={() => setUseBackup(false)}>
                        Use OTP instead
                    </Button>
                </form>
            ) : (
                <div className="space-y-4">
                    {challengeSent && <OtpInput autoFocus onComplete={handleVerify} disabled={authLoading.value} />}

                    {challengeSent && showTrustCheckbox && (
                        <TrustDeviceCheckbox id="wsms-trust-device" checked={trustDevice}
                            onChange={(e) => setTrustDevice(e.target.checked)}
                            disabled={authLoading.value} ttl={trustedDevices?.ttl} />
                    )}

                    <div className="flex justify-center gap-4 flex-wrap">
                        {challengeSent && challengeMeta.value?.requires_delivery !== false && (
                            <Button
                                variant="link"
                                type="button"
                                onClick={handleResend}
                                disabled={resendCooldown > 0}
                            >
                                {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend code'}
                            </Button>
                        )}

                        {factors.length > 1 && (
                            <Button variant="link" type="button" onClick={() => setShowFactorPicker(true)}>
                                Use a different method
                            </Button>
                        )}

                        <Button variant="link" type="button" onClick={() => setUseBackup(true)}>
                            Use a backup code
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
