import { useState, useEffect, useRef } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
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
    const hasPhone = enabledChannels.value.includes('phone');

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
                setSuccess(res.message || __('Profile updated.', 'wp-sms'));
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err).message);
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
                setSuccess(__('Avatar updated.', 'wp-sms'));
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err).message);
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
            setSuccess(__('Avatar removed.', 'wp-sms'));
            await refreshUser();
        } catch (err) {
            setError(extractError(err).message);
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
            setError(extractError(err).message);
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
            setError(extractError(err).message);
        } finally {
            setPhoneSending(false);
        }
    }

    async function handleVerified(channel) {
        if (channel === 'email') setShowEmailOtp(false);
        if (channel === 'phone') setShowPhoneOtp(false);
        setSuccess(channel === 'email' ? __('Email verified successfully.', 'wp-sms') : __('Phone verified successfully.', 'wp-sms'));
        await refreshUser();
    }

    if (!authed) return null;

    return (
        <AccountLayout title={__('Profile', 'wp-sms')} subtitle={__('Manage your account information', 'wp-sms')} currentPath="/profile">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="wsms-auth-mb-4" />
            <Alert variant="success" message={success} className="wsms-auth-mb-4" />

            {/* Avatar Section */}
            {user && (
                <div className="wsms-auth-avatar-section">
                    <UserAvatar user={user} size="lg" />
                    <div className="wsms-auth-avatar-section__actions">
                        <div className="wsms-auth-avatar-section__buttons">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={avatarUploading}
                            >
                                {avatarUploading ? __('Uploading\u2026', 'wp-sms') : __('Upload', 'wp-sms')}
                            </Button>
                            {user.avatar_url && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleAvatarRemove}
                                    disabled={avatarUploading}
                                >
                                    {__('Remove', 'wp-sms')}
                                </Button>
                            )}
                        </div>
                        <p className="wsms-auth-text-xs wsms-auth-text-muted">{__('JPG, PNG, GIF, or WebP. Max 2MB.', 'wp-sms')}</p>
                    </div>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        className="wsms-auth-hidden"
                        onChange={handleAvatarUpload}
                    />
                </div>
            )}

            <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                {/* Section 1: Personal Information */}
                <div className="wsms-auth-section-group">
                    <h3 className="wsms-auth-section-title">{__('Personal Information', 'wp-sms')}</h3>

                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prof-name">{__('Display Name', 'wp-sms')}</Label>
                        <Input
                            id="wsms-prof-name"
                            type="text"
                            value={form.display_name}
                            onInput={(e) => updateField('display_name', e.target.value)}
                            disabled={loading}
                            autoComplete="name"
                        />
                    </div>

                    <div className="wsms-auth-profile-grid">
                        <div className="wsms-auth-stack-2">
                            <Label for="wsms-prof-first-name">{__('First Name', 'wp-sms')}</Label>
                            <Input
                                id="wsms-prof-first-name"
                                type="text"
                                value={form.first_name}
                                onInput={(e) => updateField('first_name', e.target.value)}
                                disabled={loading}
                                autoComplete="given-name"
                            />
                        </div>

                        <div className="wsms-auth-stack-2">
                            <Label for="wsms-prof-last-name">{__('Last Name', 'wp-sms')}</Label>
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

                {/* Section 2: Contact & Verification */}
                <div className="wsms-auth-section-group">
                    <h3 className="wsms-auth-section-title">{__('Contact & Verification', 'wp-sms')}</h3>

                    <div className="wsms-auth-stack-2">
                        <div className="wsms-auth-row-between">
                            <Label for="wsms-prof-email">{__('Email', 'wp-sms')}</Label>
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
                            placeholder={user?.has_placeholder_email ? __('Add your email address', 'wp-sms') : undefined}
                            disabled={loading}
                            autoComplete="email"
                        />
                        {enabledChannels.value.includes('email') && user && !user.has_placeholder_email && !user.email_verified && !showEmailOtp && (
                            <div>
                                {emailSent ? (
                                    <p className="wsms-auth-text-xs wsms-auth-text-green">{__('Verification email sent! Check your inbox.', 'wp-sms')}</p>
                                ) : (
                                    <Button
                                        variant="link"
                                        type="button"
                                        className="wsms-auth-verify-link"
                                        onClick={handleSendEmailVerification}
                                        disabled={emailSending}
                                    >
                                        {emailSending ? __('Sending\u2026', 'wp-sms') : __('Send verification code', 'wp-sms')}
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
                                label={__('Enter the code sent to your email', 'wp-sms')}
                                codeLength={emailCodeLength}
                                className="wsms-auth-pt-2"
                            />
                        )}
                    </div>

                    {hasPhone && (
                        <div className="wsms-auth-stack-2">
                            <div className="wsms-auth-row-between">
                                <Label>{__('Phone Number', 'wp-sms')}</Label>
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
                                    className="wsms-auth-verify-link"
                                    onClick={handleSendPhoneVerification}
                                    disabled={phoneSending}
                                >
                                    {phoneSending ? __('Sending\u2026', 'wp-sms') : __('Verify phone', 'wp-sms')}
                                </Button>
                            )}
                            {showPhoneOtp && (
                                <OtpVerifyInline
                                    verifyEndpoint="/auth/profile/verify/phone"
                                    resendEndpoint="/auth/profile/send-verification/phone"
                                    onVerified={() => handleVerified('phone')}
                                    onError={setError}
                                    label={__('Enter the code sent to your phone', 'wp-sms')}
                                    codeLength={phoneCodeLength}
                                    className="wsms-auth-pt-2"
                                />
                            )}
                        </div>
                    )}
                </div>

                {/* Section 3: Additional Information (custom fields) */}
                {customFieldDefs.length > 0 && (
                    <div className="wsms-auth-section-group">
                        <h3 className="wsms-auth-section-title">{__('Additional Information', 'wp-sms')}</h3>
                        <div className="wsms-auth-stack-4">
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
                    </div>
                )}

                <Button className="wsms-auth-full" type="submit" disabled={loading}>
                    {loading ? __('Saving\u2026', 'wp-sms') : __('Save Changes', 'wp-sms')}
                </Button>
            </form>
        </AccountLayout>
    );
}
