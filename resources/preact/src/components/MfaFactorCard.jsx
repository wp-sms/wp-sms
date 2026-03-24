import { useState, useEffect, useRef } from 'preact/hooks';
import { Smartphone, Mail, ClipboardList, Lock, Send, KeyRound, Fingerprint, X } from 'lucide-react';
import { cn } from '@/utils/cn';
import { Button } from './ui/Button';
import { Label } from './ui/Label';
import { PhoneInput } from './PhoneInput';
import { OtpInput } from './OtpInput';
import { api } from '../api/client';
import { extractError, formatWebAuthnError } from '../utils/auth';

const CHANNEL_META = {
    phone:        { label: 'Phone',        icon: Smartphone,     description: 'Receive a code via text message' },
    email:        { label: 'Email',        icon: Mail,           description: 'Receive a code via email' },
    telegram:     { label: 'Telegram',     icon: Send,           description: 'Receive a code via Telegram bot' },
    totp:         { label: 'Authenticator App', icon: KeyRound, description: 'Use an app like Google Authenticator or Authy' },
    passkey:      { label: 'Passkey',      icon: Fingerprint,    description: 'Use fingerprint, face, or security key' },
    backup_codes: { label: 'Backup Codes', icon: ClipboardList,  description: 'One-time use recovery codes' },
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
                setError('Passkeys require a secure connection (HTTPS).');
                setExpanding(true);
                return;
            }
            if (!window.PublicKeyCredential) {
                setError('Your browser does not support passkeys.');
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
                setError(res.message || 'Verification failed.');
            }
        } catch (err) {
            setError(extractError(err).message);
        } finally {
            setLoading(false);
        }
    }

    async function handleRemoveCredential(credentialId) {
        const confirmed = window.confirm('Remove this passkey? If it is your only passkey, passkey authentication will be disabled.');
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
            totp: 'Are you sure you want to disable your authenticator app?',
            passkey: 'Are you sure you want to disable your passkey? All registered passkeys will be removed.',
        };
        const msg = confirmMessages[method.id];
        if (msg && !window.confirm(msg + ' If this is your only MFA method, multi-factor authentication will be turned off for your account.')) {
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
                'rounded-lg border transition-colors overflow-hidden',
                enrolled ? 'border-success/50 bg-success/5' : 'border-border',
            )}
        >
            <div className="flex items-center gap-3 p-4">
                <meta.icon className="size-5 shrink-0 text-muted-foreground" />
                <div className="flex-1 min-w-0">
                    <div className="text-sm font-semibold">{meta.label}</div>
                    <div className="text-xs text-muted-foreground">{meta.description}</div>
                </div>
                <div className="shrink-0 flex gap-2">
                    {enrolled && method.id === 'passkey' && (
                        <Button variant="outline" size="sm" onClick={handleEnable} disabled={loading}>
                            Add
                        </Button>
                    )}
                    {enrolled ? (
                        <Button variant="outline" size="sm" onClick={handleDisable} className="text-destructive hover:text-destructive">
                            Disable
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" onClick={handleEnable} disabled={loading}>
                            Enable
                        </Button>
                    )}
                </div>
            </div>

            {expanding && !enrolled && method.id === 'phone' && !verifying && (
                <div className="px-4 pb-4 space-y-3 animate-fade-in">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <div className="space-y-2">
                        <Label>Phone Number</Label>
                        <PhoneInput value={phone} onChange={setPhone} disabled={loading} />
                    </div>
                    <Button size="sm" onClick={handleEnable} disabled={loading || !phone}>
                        {loading ? 'Sending\u2026' : 'Send Verification Code'}
                    </Button>
                </div>
            )}

            {expanding && !enrolled && method.id === 'telegram' && telegramLink && (
                <div className="px-4 pb-4 space-y-3 animate-fade-in">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <p className="text-sm text-muted-foreground">
                        Click the button below to open Telegram and link your account.
                    </p>
                    <a
                        href={telegramLink}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 rounded-md bg-[#26A5E4] px-4 py-2 text-sm font-medium text-white hover:bg-[#1E96D1] transition-colors"
                    >
                        <Send className="size-4" />
                        Open in Telegram
                    </a>
                    <p className="text-xs text-muted-foreground">Waiting for confirmation...</p>
                </div>
            )}

            {expanding && !enrolled && method.id === 'totp' && totpEnroll && (
                <div className="px-4 pb-4 space-y-4 animate-fade-in">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <div className="flex justify-center">
                        <img src={totpEnroll.qrCodeUri} alt="QR code for authenticator app setup. Use the manual key below if you cannot scan." className="w-48 h-48" />
                    </div>
                    <details className="text-sm">
                        <summary className="cursor-pointer text-muted-foreground">
                            Can't scan? Enter this key manually
                        </summary>
                        <code className="mt-2 block rounded bg-muted p-2 text-xs font-mono break-all select-all" aria-label="Manual setup key for authenticator app">
                            {totpEnroll.secret}
                        </code>
                    </details>
                    <p className="text-sm text-muted-foreground">
                        Enter the 6-digit code from your app to verify setup
                    </p>
                    <OtpInput onComplete={(code) => handleVerifyEnrollment('totp', code)} disabled={loading} />
                </div>
            )}

            {expanding && method.id === 'passkey' && (
                <div className="px-4 pb-4 space-y-3 animate-fade-in text-center">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <Fingerprint className="mx-auto size-10 text-muted-foreground animate-pulse" />
                    <p className="text-sm text-muted-foreground">
                        {passkeyPrompting
                            ? "Follow your browser's prompts to register your passkey..."
                            : enrolled
                                ? 'Registering additional passkey...'
                                : 'Click Enable to set up your passkey'}
                    </p>
                </div>
            )}

            {enrolled && method.id === 'passkey' && passkeyCredentials.length > 0 && !expanding && (
                <div className="px-4 pb-4 space-y-2">
                    {passkeyCredentials.map((cred) => (
                        <div key={cred.id} className="flex items-center justify-between rounded-md border border-border/50 px-3 py-2">
                            <div className="min-w-0">
                                <div className="text-sm font-medium truncate">{cred.name}</div>
                                <div className="text-xs text-muted-foreground">
                                    {cred.device_type === 'multiDevice' ? 'Synced' : 'Device-bound'}
                                    {cred.backed_up && ' \u00b7 Backed up'}
                                    {cred.created_at && ` \u00b7 ${new Date(cred.created_at).toLocaleDateString()}`}
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleRemoveCredential(cred.id)}
                                className="shrink-0 p-1 rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                                aria-label={`Remove ${cred.name}`}
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {verifying && (
                <div className="px-4 pb-4 space-y-3 animate-fade-in">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <p className="text-sm text-muted-foreground">Enter the code sent to your phone</p>
                    <OtpInput onComplete={(code) => handleVerifyEnrollment('phone', code)} disabled={loading} />
                </div>
            )}
        </div>
    );
}
