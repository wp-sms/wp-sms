import { useState, useEffect, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Smartphone, Mail, ClipboardList, Lock, Send, KeyRound, Fingerprint, X } from 'lucide-react';
import { cn } from '@/utils/cn';
import { Button } from './ui/Button';
import { Label } from './ui/Label';
import { PhoneInput } from './PhoneInput';
import { OtpInput } from './OtpInput';
import { api } from '../api/client';
import { extractError, formatWebAuthnError } from '../utils/auth';

const CHANNEL_META = {
    phone:        { label: __('Phone', 'wp-sms'),        icon: Smartphone,     description: __('Receive a code via text message', 'wp-sms') },
    email:        { label: __('Email', 'wp-sms'),        icon: Mail,           description: __('Receive a code via email', 'wp-sms') },
    telegram:     { label: __('Telegram', 'wp-sms'),     icon: Send,           description: __('Receive a code via Telegram bot', 'wp-sms') },
    totp:         { label: __('Authenticator App', 'wp-sms'), icon: KeyRound, description: __('Use an app like Google Authenticator or Authy', 'wp-sms') },
    passkey:      { label: __('Passkey', 'wp-sms'),      icon: Fingerprint,    description: __('Use fingerprint, face, or security key', 'wp-sms') },
    backup_codes: { label: __('Backup Codes', 'wp-sms'), icon: ClipboardList,  description: __('One-time use recovery codes', 'wp-sms') },
};

export function MfaFactorCard({ method, enrolled, info, onEnroll, onUnenroll, onRefresh, onBackupCodes }) {
    const meta = CHANNEL_META[method.id] || { label: method.name, icon: Lock, description: '' };
    const [expanding, setExpanding] = useState(false);
    const [phone, setPhone] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [telegramLink, setTelegramLink] = useState('');
    const [totpEnroll, setTotpEnroll] = useState(null); // { qrCodeUri, secret }
    const [passkeyPrompting, setPasskeyPrompting] = useState(false);
    const pollRef = useRef(null);

    useEffect(() => {
        return () => { if (pollRef.current) clearInterval(pollRef.current); };
    }, []);

    useEffect(() => {
        if (enrolled && pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
            setExpanding(false);
            setTelegramLink('');
        }
    }, [enrolled]);

    if (method.id === 'backup_codes') return null;

    async function handleEnable() {
        setError('');

        if (method.id === 'phone' && !expanding) {
            setExpanding(true);
            return;
        }

            if (method.id === 'passkey') {
            if (!window.isSecureContext) {
                setError(__('Passkeys require a secure connection (HTTPS).', 'wp-sms'));
                setExpanding(true);
                return;
            }
            if (!window.PublicKeyCredential) {
                setError(__('Your browser does not support passkeys.', 'wp-sms'));
                setExpanding(true);
                return;
            }
        }

        setLoading(true);
        const data = method.id === 'phone' ? { phone } : {};
        const res = await onEnroll(method.id, data);

        if (res && method.id === 'phone' && res.data?.requires_verification) {
            setVerifying(true);
        }

        if (res && method.id === 'totp' && res.data?.requires_confirmation && res.data?.qr_code_uri) {
            setTotpEnroll({ qrCodeUri: res.data.qr_code_uri, secret: res.data.secret || '' });
            setExpanding(true);
        }

        if (res && method.id === 'telegram' && res.data?.deep_link) {
            setTelegramLink(res.data.deep_link);
            setExpanding(true);

            // Poll every 3s to check if enrollment completed.
            if (pollRef.current) clearInterval(pollRef.current);
            pollRef.current = setInterval(() => {
                if (onRefresh) onRefresh();
            }, 3000);
        }

        if (res && method.id === 'passkey' && res.data?.requires_confirmation && res.data?.creation_options) {
            setExpanding(true);
            setPasskeyPrompting(true);
            try {
                const { startRegistration } = await import('@simplewebauthn/browser');
                const attestation = await startRegistration({ optionsJSON: res.data.creation_options });
                await handleVerifyEnrollment('passkey', JSON.stringify(attestation));
            } catch (err) {
                setError(formatWebAuthnError(err));
            } finally {
                setPasskeyPrompting(false);
            }
        }

        setLoading(false);
    }

    async function handleVerifyEnrollment(channelId, code) {
        setError('');
        setLoading(true);

        try {
            const res = await api.post('/auth/mfa/enroll/verify', {
                channel_id: channelId,
                code,
            });
            if (res.success) {
                setExpanding(false);
                setVerifying(false);
                setTotpEnroll(null);
                setPasskeyPrompting(false);
                if (res.data?.backup_codes && onBackupCodes) {
                    onBackupCodes(res.data.backup_codes);
                }
                if (onRefresh) await onRefresh();
            } else {
                setError(res.message || __('Verification failed.', 'wp-sms'));
            }
        } catch (err) {
            setError(extractError(err).message);
        } finally {
            setLoading(false);
        }
    }

    async function handleRemoveCredential(credentialId) {
        const confirmed = window.confirm(__('Remove this passkey? If it is your only passkey, passkey authentication will be disabled.', 'wp-sms'));
        if (!confirmed) return;

        try {
            await api.del(`/auth/mfa/passkey/credential?credential_id=${encodeURIComponent(credentialId)}`);
            if (onRefresh) await onRefresh();
        } catch (err) {
            setError(extractError(err).message);
        }
    }

    function handleDisable() {
        const confirmMessages = {
            totp: __('Are you sure you want to disable your authenticator app?', 'wp-sms'),
            passkey: __('Are you sure you want to disable your passkey? All registered passkeys will be removed.', 'wp-sms'),
        };
        const msg = confirmMessages[method.id];
        if (msg && !window.confirm(msg + ' ' + __('If this is your only MFA method, multi-factor authentication will be turned off for your account.', 'wp-sms'))) {
            return;
        }

        onUnenroll(method.id);
        setExpanding(false);
        setVerifying(false);
        setTelegramLink('');
        setPasskeyPrompting(false);
        if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
    }

    const passkeyCredentials = info?.credentials || [];

    return (
        <div
            className={cn(
                'wsms-auth-mfa-card',
                enrolled && 'wsms-auth-mfa-card--enrolled',
            )}
        >
            <div className="wsms-auth-mfa-card__header">
                <meta.icon className="wsms-auth-mfa-card__icon" />
                <div className="wsms-auth-mfa-card__info">
                    <div className="wsms-auth-mfa-card__name">{meta.label}</div>
                    <div className="wsms-auth-mfa-card__desc">{meta.description}</div>
                </div>
                <div className="wsms-auth-mfa-card__actions">
                    {enrolled && method.id === 'passkey' && (
                        <Button variant="outline" size="sm" onClick={handleEnable} disabled={loading}>
                            {__('Add', 'wp-sms')}
                        </Button>
                    )}
                    {enrolled ? (
                        <Button variant="outline" size="sm" onClick={handleDisable}>
                            {__('Disable', 'wp-sms')}
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" onClick={handleEnable} disabled={loading}>
                            {__('Enable', 'wp-sms')}
                        </Button>
                    )}
                </div>
            </div>

            {expanding && !enrolled && method.id === 'phone' && !verifying && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-3 wsms-auth-fade-in">
                    {error && <p className="wsms-auth-text-sm wsms-auth-text-destructive">{error}</p>}
                    <div className="wsms-auth-stack-2">
                        <Label>{__('Phone Number', 'wp-sms')}</Label>
                        <PhoneInput value={phone} onChange={setPhone} disabled={loading} />
                    </div>
                    <Button size="sm" onClick={handleEnable} disabled={loading || !phone}>
                        {loading ? __('Sending\u2026', 'wp-sms') : __('Send Verification Code', 'wp-sms')}
                    </Button>
                </div>
            )}

            {expanding && !enrolled && method.id === 'telegram' && telegramLink && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-3 wsms-auth-fade-in">
                    {error && <p className="wsms-auth-text-sm wsms-auth-text-destructive">{error}</p>}
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Click the button below to open Telegram and link your account.', 'wp-sms')}
                    </p>
                    <a
                        href={telegramLink}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="wsms-auth-telegram-link"
                    >
                        <Send />
                        {__('Open in Telegram', 'wp-sms')}
                    </a>
                    <p className="wsms-auth-text-xs wsms-auth-text-muted">{__('Waiting for confirmation...', 'wp-sms')}</p>
                </div>
            )}

            {expanding && !enrolled && method.id === 'totp' && totpEnroll && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-4 wsms-auth-fade-in">
                    {error && <p className="wsms-auth-text-sm wsms-auth-text-destructive">{error}</p>}
                    <div className="wsms-auth-flex-center">
                        <img src={totpEnroll.qrCodeUri} alt={__('QR code for authenticator app setup. Use the manual key below if you cannot scan.', 'wp-sms')} className="wsms-auth-qr-image" />
                    </div>
                    <details className="wsms-auth-text-sm">
                        <summary className="wsms-auth-totp-summary">
                            {__('Can\'t scan? Enter this key manually', 'wp-sms')}
                        </summary>
                        <code className="wsms-auth-totp-secret" aria-label={__('Manual setup key for authenticator app', 'wp-sms')}>
                            {totpEnroll.secret}
                        </code>
                    </details>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Enter the 6-digit code from your app to verify setup', 'wp-sms')}
                    </p>
                    <OtpInput onComplete={(code) => handleVerifyEnrollment('totp', code)} disabled={loading} />
                </div>
            )}

            {expanding && method.id === 'passkey' && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-3 wsms-auth-fade-in wsms-auth-center">
                    {error && <p className="wsms-auth-text-sm wsms-auth-text-destructive">{error}</p>}
                    <Fingerprint className="wsms-auth-passkey-icon wsms-auth-passkey-icon--prompting" style={{ width: '2.5rem', height: '2.5rem' }} />
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {passkeyPrompting
                            ? __('Follow your browser\'s prompts to register your passkey...', 'wp-sms')
                            : enrolled
                                ? __('Registering additional passkey...', 'wp-sms')
                                : __('Click Enable to set up your passkey', 'wp-sms')}
                    </p>
                </div>
            )}

            {enrolled && method.id === 'passkey' && passkeyCredentials.length > 0 && !expanding && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-2">
                    {passkeyCredentials.map((cred) => (
                        <div key={cred.id} className="wsms-auth-passkey-credential">
                            <div className="wsms-auth-passkey-credential__info">
                                <div className="wsms-auth-passkey-credential__name">{cred.name}</div>
                                <div className="wsms-auth-passkey-credential__meta">
                                    {cred.device_type === 'multiDevice' ? __('Synced', 'wp-sms') : __('Device-bound', 'wp-sms')}
                                    {cred.backed_up && ' \u00b7 ' + __('Backed up', 'wp-sms')}
                                    {cred.created_at && ` \u00b7 ${new Date(cred.created_at).toLocaleDateString()}`}
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleRemoveCredential(cred.id)}
                                className="wsms-auth-passkey-credential__remove"
                                aria-label={sprintf(__('Remove %s', 'wp-sms'), cred.name)}
                            >
                                <X />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {verifying && (
                <div className="wsms-auth-mfa-card__body wsms-auth-stack-3 wsms-auth-fade-in">
                    {error && <p className="wsms-auth-text-sm wsms-auth-text-destructive">{error}</p>}
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">{__('Enter the code sent to your phone', 'wp-sms')}</p>
                    <OtpInput onComplete={(code) => handleVerifyEnrollment('phone', code)} disabled={loading} />
                </div>
            )}
        </div>
    );
}
