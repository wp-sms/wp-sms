import { useState, useEffect, useRef } from 'preact/hooks';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { methodDetails, enabledChannels, profileFieldDefs } from '../signals/config';
import { loadCurrentUser, refreshUser } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { AccountLayout } from '../layouts/AccountLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { Separator } from '../components/ui/Separator';
import { PhoneInput } from '../components/PhoneInput';
import { OtpVerifyInline } from '../components/verification/OtpVerifyInline';
import { StatusBadge } from '../components/ui/StatusBadge';
import { UserAvatar } from '../components/ui/UserAvatar';
import { DynamicField } from '../components/DynamicField';
import { SYSTEM_FIELD_IDS } from '../utils/fields';

export function Profile() {
    const authed = useAuthGuard();
    const [form, setForm] = useState({ display_name: '', first_name: '', last_name: '', email: '', phone: '' });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [emailSending, setEmailSending] = useState(false);
    const [emailSent, setEmailSent] = useState(false);
    const [showEmailOtp, setShowEmailOtp] = useState(false);
    const [phoneSending, setPhoneSending] = useState(false);
    const [showPhoneOtp, setShowPhoneOtp] = useState(false);
    const [avatarUploading, setAvatarUploading] = useState(false);
    const fileInputRef = useRef(null);

    useEffect(() => {
        if (!authed) return;

        async function init() {
            if (!currentUser.value) await loadCurrentUser();
            const u = currentUser.value;
            if (u) {
                const initial = {
                    display_name: u.display_name || '',
                    first_name: u.first_name || '',
                    last_name: u.last_name || '',
                    email: u.email || '',
                    phone: u.phone || '',
                };

                // Load custom field values.
                if (u.custom_fields) {
                    for (const [key, val] of Object.entries(u.custom_fields)) {
                        initial[key] = val || '';
                    }
                }

                setForm(initial);
            }
        }
        init();
    }, [authed]);

    const user = currentUser.value;
    const details = methodDetails.value;
    const emailCodeLength = details.email?.code_length;
    const phoneCodeLength = details.phone?.code_length;
    const customFieldDefs = profileFieldDefs.value.filter((def) => !SYSTEM_FIELD_IDS.includes(def.id));

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
        if (name === 'email') {
            setEmailSent(false);
            setShowEmailOtp(false);
        }
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

    async function handleAvatarUpload(e) {
        const file = e.target.files?.[0];
        if (!file) return;

        setAvatarUploading(true);
        setError('');

        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const res = await api.upload('/auth/profile/avatar', formData);
            if (res.success) {
                setSuccess('Avatar updated.');
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        } finally {
            setAvatarUploading(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    }

    async function handleAvatarRemove() {
        setAvatarUploading(true);
        setError('');

        try {
            await api.del('/auth/profile/avatar');
            setSuccess('Avatar removed.');
            await refreshUser();
        } catch (err) {
            setError(extractError(err));
        } finally {
            setAvatarUploading(false);
        }
    }

    async function handleSendEmailVerification() {
        setEmailSending(true);
        setError('');
        try {
            const res = await api.post('/auth/profile/send-verification/email');
            if (res.method === 'otp') {
                setShowEmailOtp(true);
            } else {
                setEmailSent(true);
            }
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
            await api.post('/auth/profile/send-verification/phone');
            setShowPhoneOtp(true);
        } catch (err) {
            setError(extractError(err));
        } finally {
            setPhoneSending(false);
        }
    }

    async function handleVerified(channel) {
        if (channel === 'email') setShowEmailOtp(false);
        if (channel === 'phone') setShowPhoneOtp(false);
        setSuccess(`${channel === 'email' ? 'Email' : 'Phone'} verified successfully.`);
        await refreshUser();
    }

    if (!authed) return null;

    return (
        <AccountLayout title="Profile" subtitle="Manage your account information" currentPath="/profile">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            {/* Avatar Section */}
            {user && (
                <div className="mb-6 flex items-center gap-4">
                    <UserAvatar user={user} size="lg" />
                    <div className="space-y-1">
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={avatarUploading}
                            >
                                {avatarUploading ? 'Uploading\u2026' : 'Upload'}
                            </Button>
                            {user.avatar_url && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleAvatarRemove}
                                    disabled={avatarUploading}
                                >
                                    Remove
                                </Button>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">JPG, PNG, GIF, or WebP. Max 2MB.</p>
                    </div>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        className="hidden"
                        onChange={handleAvatarUpload}
                    />
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Section 1: Personal Information */}
                <div className="space-y-4">
                    <h3 className="text-sm font-semibold text-foreground">Personal Information</h3>

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

                    <div className="grid gap-4 sm:grid-cols-2">
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
                    </div>
                </div>

                <Separator />

                {/* Section 2: Contact & Verification */}
                <div className="space-y-4">
                    <h3 className="text-sm font-semibold text-foreground">Contact & Verification</h3>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label for="wsms-prof-email">Email</Label>
                            {user && !user.has_placeholder_email && (
                                <StatusBadge variant={user.email_verified ? 'verified' : 'unverified'} />
                            )}
                        </div>
                        <Input
                            id="wsms-prof-email"
                            type="email"
                            value={form.email}
                            onInput={(e) => updateField('email', e.target.value)}
                            required={!user?.has_placeholder_email}
                            placeholder={user?.has_placeholder_email ? 'Add your email address' : undefined}
                            disabled={loading}
                            autoComplete="email"
                        />
                        {enabledChannels.value.includes('email') && user && !user.has_placeholder_email && !user.email_verified && !showEmailOtp && (
                            <div>
                                {emailSent ? (
                                    <p className="text-xs text-green-600">Verification email sent! Check your inbox.</p>
                                ) : (
                                    <Button
                                        variant="link"
                                        type="button"
                                        className="h-auto p-0 text-xs"
                                        onClick={handleSendEmailVerification}
                                        disabled={emailSending}
                                    >
                                        {emailSending ? 'Sending\u2026' : 'Send verification code'}
                                    </Button>
                                )}
                            </div>
                        )}
                        {enabledChannels.value.includes('email') && showEmailOtp && (
                            <OtpVerifyInline
                                verifyEndpoint="/auth/profile/verify/email"
                                resendEndpoint="/auth/profile/send-verification/email"
                                onVerified={() => handleVerified('email')}
                                onError={setError}
                                label="Enter the code sent to your email"
                                codeLength={emailCodeLength}
                                className="pt-2"
                            />
                        )}
                    </div>

                    {enabledChannels.value.includes('phone') && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Phone Number</Label>
                                {user && user.phone && (
                                    <StatusBadge variant={user.phone_verified ? 'verified' : 'unverified'} />
                                )}
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
                                <OtpVerifyInline
                                    verifyEndpoint="/auth/profile/verify/phone"
                                    resendEndpoint="/auth/profile/send-verification/phone"
                                    onVerified={() => handleVerified('phone')}
                                    onError={setError}
                                    label="Enter the code sent to your phone"
                                    codeLength={phoneCodeLength}
                                    className="pt-2"
                                />
                            )}
                        </div>
                    )}
                </div>

                {/* Section 3: Additional Information (custom fields) */}
                {customFieldDefs.length > 0 && (
                    <>
                        <Separator />
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold text-foreground">Additional Information</h3>
                            {customFieldDefs.map((def) => (
                                <DynamicField
                                    key={def.id}
                                    field={def}
                                    value={form[def.id]}
                                    onChange={(val) => updateField(def.id, val)}
                                    disabled={loading}
                                />
                            ))}
                        </div>
                    </>
                )}

                <Button className="w-full" type="submit" disabled={loading}>
                    {loading ? 'Saving\u2026' : 'Save Changes'}
                </Button>
            </form>
        </AccountLayout>
    );
}
