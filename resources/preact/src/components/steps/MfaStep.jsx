import { useState, useEffect, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
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
    return sprintf(days === 1 ? __('%d day', 'wp-sms') : __('%d days', 'wp-sms'), days);
}

function TrustDeviceCheckbox({ id, checked, onChange, disabled, ttl }) {
    return (
        <div className="wsms-auth-trust-device">
            <input type="checkbox" id={id} checked={checked}
                onChange={onChange}
                disabled={disabled}
                className="wsms-auth-checkbox" />
            <label for={id} className="wsms-auth-trust-device__label">
                {__('Remember this device', 'wp-sms')}
                <span className="wsms-auth-trust-device__hint">
                    {sprintf(__('Skip verification for %s', 'wp-sms'), formatTtl(ttl))}
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
    const tryAgainRef = useRef(null);

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
            // Focus the try-again button after passkey error
            requestAnimationFrame(() => tryAgainRef.current?.focus());
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
        subtitle = __('Use your passkey to verify', 'wp-sms');
    } else if (challengeMeta.value?.requires_delivery === false) {
        subtitle = __('Enter the code from your authenticator app', 'wp-sms');
    } else if (challengeMeta.value?.masked_identifier) {
        subtitle = sprintf(__('Enter the code sent to %s', 'wp-sms'), challengeMeta.value.masked_identifier);
    } else {
        subtitle = __('Enter your verification code to continue.', 'wp-sms');
    }

    if (showFactorPicker) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">{__('Choose a verification method', 'wp-sms')}</p>
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
                    {__('Cancel', 'wp-sms')}
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
                    {showTrustCheckbox && (
                        <TrustDeviceCheckbox id="wsms-trust-device-backup" checked={trustDevice}
                            onChange={(e) => setTrustDevice(e.target.checked)}
                            disabled={authLoading.value} ttl={trustedDevices?.ttl} />
                    )}
                    <Button className="wsms-auth-full" type="submit" loading={authLoading.value} disabled={!backupCode.trim()}>
                        {authLoading.value ? __('Verifying...', 'wp-sms') : __('Verify Backup Code', 'wp-sms')}
                    </Button>
                    <Button variant="link" type="button" className="wsms-auth-full" onClick={() => setUseBackup(false)}>
                        {__('Use OTP instead', 'wp-sms')}
                    </Button>
                </form>
            ) : (
                <div className="wsms-auth-stack-4">
                    {/* Passkey verification UI */}
                    {isPasskey ? (
                        <div className="wsms-auth-stack-4 wsms-auth-center">
                            <Fingerprint className={cn('wsms-auth-passkey-icon', passkeyPrompting && 'wsms-auth-passkey-icon--prompting')} style={{ width: '3rem', height: '3rem' }} />
                            <p className="wsms-auth-text-sm wsms-auth-text-muted">
                                {passkeyPrompting ? __('Verify with your passkey...', 'wp-sms') : __('Ready to verify', 'wp-sms')}
                            </p>
                            {!passkeyPrompting && (
                                <Button
                                    ref={tryAgainRef}
                                    variant="outline"
                                    onClick={() => sendMfaChallenge('passkey')}
                                    disabled={authLoading.value}
                                >
                                    {__('Try Again', 'wp-sms')}
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
                            {challengeSent && <OtpInput autoFocus onComplete={handleVerify} disabled={authLoading.value} error={!!authError.value} />}

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
                                {resendCooldown > 0 ? sprintf(__('Resend in %ds', 'wp-sms'), resendCooldown) : __('Resend code', 'wp-sms')}
                            </Button>
                        )}

                        {factors.length > 1 && (
                            <Button variant="link" type="button" onClick={() => setShowFactorPicker(true)}>
                                {__('Use a different method', 'wp-sms')}
                            </Button>
                        )}

                        {factors.some(f => f.channel_id === 'backup_codes') && (
                            <Button variant="link" type="button" onClick={() => setUseBackup(true)}>
                                {__('Use a backup code', 'wp-sms')}
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
