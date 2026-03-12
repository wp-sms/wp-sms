import { useState, useEffect } from 'preact/hooks';
import { CheckCircle2 } from 'lucide-react';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, refreshUser } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { AccountLayout } from '../layouts/AccountLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { PhoneInput } from '../components/PhoneInput';
import { OtpInput } from '../components/OtpInput';

function VerifiedBadge() {
    return (
        <span className="inline-flex items-center gap-1 text-xs font-medium text-green-600">
            <CheckCircle2 className="size-3.5" />
            Verified
        </span>
    );
}

function UnverifiedBadge() {
    return (
        <span className="inline-flex items-center gap-1 text-xs font-medium text-yellow-600">
            Unverified
        </span>
    );
}

export function Profile() {
    const authed = useAuthGuard();
    const [form, setForm] = useState({ display_name: '', first_name: '', last_name: '', email: '', phone: '' });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [emailSending, setEmailSending] = useState(false);
    const [emailSent, setEmailSent] = useState(false);
    const [phoneSending, setPhoneSending] = useState(false);
    const [showPhoneOtp, setShowPhoneOtp] = useState(false);
    const [phoneVerifying, setPhoneVerifying] = useState(false);

    useEffect(() => {
        if (!authed) return;

        async function init() {
            if (!currentUser.value) await loadCurrentUser();
            const u = currentUser.value;
            if (u) {
                setForm({
                    display_name: u.display_name || '',
                    first_name: u.first_name || '',
                    last_name: u.last_name || '',
                    email: u.email || '',
                    phone: u.phone || '',
                });
            }
        }
        init();
    }, [authed]);

    const user = currentUser.value;

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setError('');
        setSuccess('');
        setLoading(true);

        try {
            const res = await api.put('/auth/profile', form);
            if (res.success) {
                setSuccess(res.message || 'Profile updated.');
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        } finally {
            setLoading(false);
        }
    }

    async function handleSendEmailVerification() {
        setEmailSending(true);
        setError('');
        try {
            await api.post('/auth/profile/send-email-verification');
            setEmailSent(true);
        } catch (err) {
            setError(extractError(err));
        } finally {
            setEmailSending(false);
        }
    }

    async function handleSendPhoneVerification() {
        setPhoneSending(true);
        setError('');
        try {
            await api.post('/auth/profile/send-phone-verification');
            setShowPhoneOtp(true);
        } catch (err) {
            setError(extractError(err));
        } finally {
            setPhoneSending(false);
        }
    }

    async function handlePhoneOtpComplete(code) {
        setPhoneVerifying(true);
        setError('');
        try {
            const res = await api.post('/auth/profile/verify-phone', { code });
            if (res.success) {
                setShowPhoneOtp(false);
                setSuccess('Phone verified successfully.');
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        } finally {
            setPhoneVerifying(false);
        }
    }

    if (!authed) return null;

    return (
        <AccountLayout title="Profile" subtitle="Manage your account information" currentPath="/profile">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                    <Label for="wsms-prof-name">Display Name</Label>
                    <Input
                        id="wsms-prof-name"
                        type="text"
                        value={form.display_name}
                        onInput={(e) => updateField('display_name', e.target.value)}
                        disabled={loading}
                        autoComplete="name"
                    />
                </div>

                <div className="space-y-2">
                    <Label for="wsms-prof-first-name">First Name</Label>
                    <Input
                        id="wsms-prof-first-name"
                        type="text"
                        value={form.first_name}
                        onInput={(e) => updateField('first_name', e.target.value)}
                        disabled={loading}
                        autoComplete="given-name"
                    />
                </div>

                <div className="space-y-2">
                    <Label for="wsms-prof-last-name">Last Name</Label>
                    <Input
                        id="wsms-prof-last-name"
                        type="text"
                        value={form.last_name}
                        onInput={(e) => updateField('last_name', e.target.value)}
                        disabled={loading}
                        autoComplete="family-name"
                    />
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label for="wsms-prof-email">Email</Label>
                        {user && (user.email_verified ? <VerifiedBadge /> : <UnverifiedBadge />)}
                    </div>
                    <Input
                        id="wsms-prof-email"
                        type="email"
                        value={form.email}
                        onInput={(e) => updateField('email', e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="email"
                    />
                    {user && !user.email_verified && (
                        <div>
                            {emailSent ? (
                                <p className="text-xs text-green-600">Verification email sent!</p>
                            ) : (
                                <Button
                                    variant="link"
                                    type="button"
                                    className="h-auto p-0 text-xs"
                                    onClick={handleSendEmailVerification}
                                    disabled={emailSending}
                                >
                                    {emailSending ? 'Sending\u2026' : 'Send verification email'}
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label>Phone Number</Label>
                        {user && user.phone && (user.phone_verified ? <VerifiedBadge /> : <UnverifiedBadge />)}
                    </div>
                    <PhoneInput
                        value={form.phone}
                        onChange={(val) => updateField('phone', val)}
                        disabled={loading}
                    />
                    {user && user.phone && !user.phone_verified && !showPhoneOtp && (
                        <Button
                            variant="link"
                            type="button"
                            className="h-auto p-0 text-xs"
                            onClick={handleSendPhoneVerification}
                            disabled={phoneSending}
                        >
                            {phoneSending ? 'Sending\u2026' : 'Verify phone'}
                        </Button>
                    )}
                    {showPhoneOtp && (
                        <div className="space-y-2 pt-2">
                            <p className="text-xs text-muted-foreground">Enter the code sent to your phone</p>
                            <OtpInput onComplete={handlePhoneOtpComplete} disabled={phoneVerifying} />
                        </div>
                    )}
                </div>

                <Button className="w-full" type="submit" disabled={loading}>
                    {loading ? 'Saving\u2026' : 'Save Changes'}
                </Button>
            </form>
        </AccountLayout>
    );
}
