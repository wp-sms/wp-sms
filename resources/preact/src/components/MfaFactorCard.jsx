import { useState } from 'preact/hooks';
import { PhoneInput } from './PhoneInput';
import { OtpInput } from './OtpInput';
import { api } from '../api/client';
import { extractError } from '../utils/auth';

const CHANNEL_META = {
    sms:          { label: 'SMS OTP',      icon: '\u{1F4F1}', description: 'Receive a code via text message' },
    email_otp:    { label: 'Email OTP',    icon: '\u{2709}\u{FE0F}', description: 'Receive a code via email' },
    backup_codes: { label: 'Backup Codes', icon: '\u{1F4CB}', description: 'One-time use recovery codes' },
};

export function MfaFactorCard({ method, enrolled, info, onEnroll, onUnenroll, onRefresh }) {
    const meta = CHANNEL_META[method.id] || { label: method.name, icon: '\u{1F512}', description: '' };
    const [expanding, setExpanding] = useState(false);
    const [phone, setPhone] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    // Don't show backup_codes as a standalone card — handled separately in Security.jsx
    if (method.id === 'backup_codes') return null;

    async function handleEnable() {
        setError('');

        if (method.id === 'sms' && !expanding) {
            setExpanding(true);
            return;
        }

        setLoading(true);
        const data = method.id === 'sms' ? { phone } : {};
        const res = await onEnroll(method.id, data);

        if (res && method.id === 'sms' && res.data?.requires_verification) {
            setVerifying(true);
        }

        setLoading(false);
    }

    async function handleVerifySms(code) {
        setError('');
        setLoading(true);

        try {
            const res = await api.post('/auth/mfa/enroll/verify', {
                channel_id: 'sms',
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
    }

    return (
        <div class={`wsms-factor-card ${enrolled ? 'is-enrolled' : ''}`}>
            <div class="wsms-factor-card__header">
                <span class="wsms-factor-card__icon">{meta.icon}</span>
                <div class="wsms-factor-card__info">
                    <strong>{meta.label}</strong>
                    <p>{meta.description}</p>
                </div>
                <div class="wsms-factor-card__actions">
                    {enrolled ? (
                        <button
                            type="button"
                            class="wsms-btn wsms-btn--secondary wsms-btn--sm wsms-btn--danger"
                            onClick={handleDisable}
                        >
                            Disable
                        </button>
                    ) : (
                        <button
                            type="button"
                            class="wsms-btn wsms-btn--secondary wsms-btn--sm"
                            onClick={handleEnable}
                            disabled={loading}
                        >
                            Enable
                        </button>
                    )}
                </div>
            </div>

            {expanding && !enrolled && method.id === 'sms' && !verifying && (
                <div class="wsms-factor-card__body">
                    {error && <p class="wsms-text-error">{error}</p>}
                    <div class="wsms-field">
                        <label class="wsms-label">Phone Number</label>
                        <PhoneInput value={phone} onChange={setPhone} disabled={loading} />
                    </div>
                    <button
                        type="button"
                        class="wsms-btn wsms-btn--primary wsms-btn--sm"
                        onClick={handleEnable}
                        disabled={loading || !phone}
                    >
                        {loading ? 'Sending…' : 'Send Verification Code'}
                    </button>
                </div>
            )}

            {verifying && (
                <div class="wsms-factor-card__body">
                    {error && <p class="wsms-text-error">{error}</p>}
                    <p class="wsms-text-secondary">Enter the code sent to your phone</p>
                    <OtpInput onComplete={handleVerifySms} disabled={loading} />
                </div>
            )}
        </div>
    );
}
