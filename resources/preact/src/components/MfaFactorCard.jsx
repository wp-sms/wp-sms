import { useState, useEffect, useRef } from 'preact/hooks';
import { Smartphone, Mail, ClipboardList, Lock, Send } from 'lucide-react';
import { cn } from '@/utils/cn';
import { Button } from './ui/Button';
import { Label } from './ui/Label';
import { PhoneInput } from './PhoneInput';
import { OtpInput } from './OtpInput';
import { api } from '../api/client';
import { extractError } from '../utils/auth';

const CHANNEL_META = {
    phone:        { label: 'Phone',        icon: Smartphone,     description: 'Receive a code via text message' },
    email:        { label: 'Email',        icon: Mail,           description: 'Receive a code via email' },
    telegram:     { label: 'Telegram',     icon: Send,           description: 'Receive a code via Telegram bot' },
    backup_codes: { label: 'Backup Codes', icon: ClipboardList,  description: 'One-time use recovery codes' },
};

export function MfaFactorCard({ method, enrolled, info, onEnroll, onUnenroll, onRefresh }) {
    const meta = CHANNEL_META[method.id] || { label: method.name, icon: Lock, description: '' };
    const [expanding, setExpanding] = useState(false);
    const [phone, setPhone] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [telegramLink, setTelegramLink] = useState('');
    const pollRef = useRef(null);

    // Poll for Telegram enrollment completion.
    useEffect(() => {
        return () => { if (pollRef.current) clearInterval(pollRef.current); };
    }, []);

    if (method.id === 'backup_codes') return null;

    async function handleEnable() {
        setError('');

        if (method.id === 'phone' && !expanding) {
            setExpanding(true);
            return;
        }

        setLoading(true);
        const data = method.id === 'phone' ? { phone } : {};
        const res = await onEnroll(method.id, data);

        if (res && method.id === 'phone' && res.data?.requires_verification) {
            setVerifying(true);
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

        setLoading(false);
    }

    async function handleVerifyPhone(code) {
        setError('');
        setLoading(true);

        try {
            const res = await api.post('/auth/mfa/enroll/verify', {
                channel_id: 'phone',
                code,
            });
            if (res.success) {
                setExpanding(false);
                setVerifying(false);
                if (onRefresh) await onRefresh();
            } else {
                setError(res.message || 'Verification failed.');
            }
        } catch (err) {
            setError(extractError(err));
        } finally {
            setLoading(false);
        }
    }

    function handleDisable() {
        onUnenroll(method.id);
        setExpanding(false);
        setVerifying(false);
        setTelegramLink('');
        if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
    }

    // Stop polling once enrolled.
    useEffect(() => {
        if (enrolled && pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
            setExpanding(false);
            setTelegramLink('');
        }
    }, [enrolled]);

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
                <div className="shrink-0">
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

            {verifying && (
                <div className="px-4 pb-4 space-y-3 animate-fade-in">
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <p className="text-sm text-muted-foreground">Enter the code sent to your phone</p>
                    <OtpInput onComplete={handleVerifyPhone} disabled={loading} />
                </div>
            )}
        </div>
    );
}
