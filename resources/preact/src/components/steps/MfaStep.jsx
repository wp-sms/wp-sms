import { useState, useEffect } from 'preact/hooks';
import { Fingerprint } from 'lucide-react';
import { cn } from '../../utils/cn';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    stopLoading,
    pendingMfa,
    challengeToken,
    challengeMeta,
    selectedMethod as selectedAuthMethod,
} from '../../signals/auth';
import { trustedDevicesConfig } from '../../signals/config';
import { handleAuthResponse, extractError, handleRecoveryAction, formatWebAuthnError } from '../../utils/auth';
import { authUrl } from '../../utils/urls';
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
        <div className="wsms-auth-trust-device">
            <input type="checkbox" id={id} checked={checked}
                onChange={onChange}
                disabled={disabled}
                className="wsms-auth-checkbox" />
            <label for={id} className="wsms-auth-trust-device__label">
                Remember this device
                <span className="wsms-auth-trust-device__hint">
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
    const [passkeyPrompting, setPasskeyPrompting] = useState(false);
    const backupRef = useAutoFocus(useBackup);

    const trustedDevices = trustedDevicesConfig.value;
    const showTrustCheckbox = trustedDevices?.enabled;

    useEffect(() => {
        if (!token || factors.length === 0) return;

        // Pick smart default: prefer passkey (instant, phishing-resistant), then other non-primary channels.
        const primaryMethod = selectedAuthMethod.value || 'password';
        const primaryChannel = primaryMethod.startsWith('phone') ? 'phone' : primaryMethod.startsWith('email') ? 'email' : 'password';

        let defaultFactor = factors.find((f) => f.channel_id === 'passkey')
            || factors.find((f) => f.channel_id !== primaryChannel && f.channel_id !== 'backup_codes')
            || factors[0];

        setActiveFactor(defaultFactor.channel_id);
        sendMfaChallenge(defaultFactor.channel_id);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (resendCooldown <= 0) return;
        const timer = setTimeout(() => setResendCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [resendCooldown]);

    if (!token) return null;

    const recoveryOpts = { setCooldown: setResendCooldown };

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

            if (res.meta?.channel_type === 'passkey') {
                authLoading.value = false;
                await triggerPasskeyVerification(res.meta.assertion_options, channelId);
                return;
            }
        } catch (err) {
            const details = extractError(err);
            if (!handleRecoveryAction(details, route, recoveryOpts)) {
                authError.value = details.message;
            }
        } finally {
            stopLoading();
        }
    }

    async function triggerPasskeyVerification(assertionOptions, channelId) {
        setPasskeyPrompting(true);
        try {
            const { startAuthentication } = await import('@simplewebauthn/browser');
            const assertion = await startAuthentication({ optionsJSON: assertionOptions });
            await handleVerify(JSON.stringify(assertion), channelId);
        } catch (err) {
            authError.value = formatWebAuthnError(err);
        } finally {
            setPasskeyPrompting(false);
        }
    }

    async function handleVerify(code, channelId) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/mfa/verify', {
                session_token: token,
                code,
                channel_id: useBackup ? 'backup_codes' : (channelId || activeFactor),
                ...(trustDevice && { trust_device: true }),
            });
            handleAuthResponse(res, route);
        } catch (err) {
            const details = extractError(err);
            if (!handleRecoveryAction(details, route, recoveryOpts)) {
                authError.value = details.message;
            }
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
            const details = extractError(err);
            if (!handleRecoveryAction(details, route, recoveryOpts)) {
                authError.value = details.message;
            }
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
        setPasskeyPrompting(false);
        sendMfaChallenge(channelId);
    }

    const isPasskey = activeFactor === 'passkey';

    let subtitle;
    if (isPasskey) {
        subtitle = 'Use your passkey to verify';
    } else if (challengeMeta.value?.requires_delivery === false) {
        subtitle = 'Enter the code from your authenticator app';
    } else if (challengeMeta.value?.masked_identifier) {
        subtitle = `Enter the code sent to ${challengeMeta.value.masked_identifier}`;
    } else {
        subtitle = 'Enter your verification code to continue.';
    }

    if (showFactorPicker) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">Choose a verification method</p>
                {factors.map((f) => (
                    <Button
                        key={f.channel_id}
                        variant="outline"
                        className="wsms-auth-full"
                        onClick={() => switchFactor(f.channel_id)}
                    >
                        {f.name}
                    </Button>
                ))}
                <Button variant="link" className="wsms-auth-full" onClick={() => setShowFactorPicker(false)}>
                    Cancel
                </Button>
            </div>
        );
    }

    return (
        <div className="wsms-auth-stack-4 wsms-auth-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />

            <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">{subtitle}</p>

            {useBackup ? (
                <form onSubmit={handleBackupSubmit} className="wsms-auth-stack-4">
                    <div className="wsms-auth-stack-2">
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
                    <Button className="wsms-auth-full" type="submit" disabled={authLoading.value || !backupCode.trim()}>
                        {authLoading.value ? 'Verifying...' : 'Verify Backup Code'}
                    </Button>
                    <Button variant="link" type="button" className="wsms-auth-full" onClick={() => setUseBackup(false)}>
                        Use OTP instead
                    </Button>
                </form>
            ) : (
                <div className="wsms-auth-stack-4">
                    {/* Passkey verification UI */}
                    {isPasskey ? (
                        <div className="wsms-auth-stack-4 wsms-auth-center">
                            <Fingerprint className={cn('wsms-auth-passkey-icon', passkeyPrompting && 'wsms-auth-passkey-icon--prompting')} style={{ width: '3rem', height: '3rem' }} />
                            <p className="wsms-auth-text-sm wsms-auth-text-muted">
                                {passkeyPrompting ? 'Verify with your passkey...' : 'Ready to verify'}
                            </p>
                            {!passkeyPrompting && (
                                <Button
                                    variant="outline"
                                    onClick={() => sendMfaChallenge('passkey')}
                                    disabled={authLoading.value}
                                >
                                    Try Again
                                </Button>
                            )}
                            {showTrustCheckbox && (
                                <TrustDeviceCheckbox id="wsms-trust-device-passkey" checked={trustDevice}
                                    onChange={(e) => setTrustDevice(e.target.checked)}
                                    disabled={authLoading.value} ttl={trustedDevices?.ttl} />
                            )}
                        </div>
                    ) : (
                        <>
                            {challengeSent && <OtpInput autoFocus onComplete={handleVerify} disabled={authLoading.value} />}

                            {challengeSent && showTrustCheckbox && (
                                <TrustDeviceCheckbox id="wsms-trust-device" checked={trustDevice}
                                    onChange={(e) => setTrustDevice(e.target.checked)}
                                    disabled={authLoading.value} ttl={trustedDevices?.ttl} />
                            )}
                        </>
                    )}

                    <div className="wsms-auth-flex-wrap">
                        {challengeSent && !isPasskey && challengeMeta.value?.requires_delivery !== false && (
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

                        {factors.some(f => f.channel_id === 'backup_codes') && (
                            <Button variant="link" type="button" onClick={() => setUseBackup(true)}>
                                Use a backup code
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
