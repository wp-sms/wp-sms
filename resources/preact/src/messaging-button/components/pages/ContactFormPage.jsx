import { useState } from 'preact/hooks';
import { submitMessage } from '../../api/widget-client';
import { CheckCircleIcon } from '../icons';

const FIELD_CONFIG = [
    { id: 'name', label: 'Name', type: 'text', placeholder: 'Your name' },
    { id: 'email', label: 'Email', type: 'email', placeholder: 'your@email.com' },
    { id: 'phone', label: 'Phone', type: 'tel', placeholder: '+1 (555) 000-0000' },
    { id: 'message', label: 'Message', type: 'textarea', placeholder: 'How can we help?' },
];

export function ContactFormPage({ config, gdpr, onClose }) {
    const [formData, setFormData] = useState({ name: '', email: '', phone: '', message: '' });
    const [gdprConsent, setGdprConsent] = useState(false);
    const [status, setStatus] = useState('idle'); // idle | sending | sent | error
    const [errorMsg, setErrorMsg] = useState('');

    const fields = config.fields ?? ['name', 'email', 'phone', 'message'];
    const requiredFields = config.required_fields ?? ['email', 'message'];

    const handleChange = (field, value) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('sending');
        setErrorMsg('');

        const trimmed = Object.fromEntries(
            Object.entries(formData).map(([k, v]) => [k, v.trim()])
        );

        try {
            await submitMessage({
                ...trimmed,
                page_url: window.location.href,
                gdpr_consent: gdpr.enabled ? gdprConsent : true,
            });
            setStatus('sent');
        } catch (err) {
            setStatus('error');
            setErrorMsg(err?.error || err?.message || 'Failed to send message. Please try again.');
        }
    };

    if (status === 'sent') {
        return (
            <div class="wsms-mb-page wsms-mb-page--form">
                <div class="wsms-mb-form__success" aria-live="polite">
                    <div class="wsms-mb-form__success-icon">
                        <CheckCircleIcon size={32} />
                    </div>
                    <h3>Message sent!</h3>
                    <p>We'll get back to you as soon as possible.</p>
                    <button
                        type="button"
                        class="wsms-mb-btn wsms-mb-btn--secondary"
                        onClick={onClose}
                    >
                        Close
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div class="wsms-mb-page wsms-mb-page--form">
            <form onSubmit={handleSubmit} class="wsms-mb-form" noValidate>
                {FIELD_CONFIG.filter(({ id }) => fields.includes(id)).map(({ id, label, type, placeholder }) => {
                    const isRequired = requiredFields.includes(id);
                    const htmlId = `wsms-mb-${id}`;
                    const InputTag = type === 'textarea' ? 'textarea' : 'input';
                    return (
                        <div key={id} class="wsms-mb-field">
                            <label class="wsms-mb-label" for={htmlId}>
                                {label} {isRequired && <span class="wsms-mb-required">*</span>}
                            </label>
                            <InputTag
                                id={htmlId}
                                type={type !== 'textarea' ? type : undefined}
                                class={type === 'textarea' ? 'wsms-mb-textarea' : 'wsms-mb-input'}
                                value={formData[id]}
                                onInput={(e) => handleChange(id, e.target.value)}
                                required={isRequired}
                                placeholder={placeholder}
                                rows={type === 'textarea' ? 3 : undefined}
                            />
                        </div>
                    );
                })}

                {gdpr.enabled && (
                    <div class="wsms-mb-field wsms-mb-field--checkbox">
                        <label class="wsms-mb-checkbox-label">
                            <input
                                type="checkbox"
                                checked={gdprConsent}
                                onChange={(e) => setGdprConsent(e.target.checked)}
                            />
                            <span>
                                {gdpr.link_url ? (
                                    <a href={gdpr.link_url} target="_blank" rel="noopener noreferrer">
                                        {gdpr.consent_text || 'I agree to the privacy policy.'}
                                    </a>
                                ) : (
                                    gdpr.consent_text || 'I agree to the privacy policy.'
                                )}
                            </span>
                        </label>
                    </div>
                )}

                {errorMsg && (
                    <div class="wsms-mb-form__error" role="alert">{errorMsg}</div>
                )}

                <button
                    type="submit"
                    class="wsms-mb-btn wsms-mb-btn--primary"
                    disabled={status === 'sending' || (gdpr.enabled && !gdprConsent)}
                    aria-busy={status === 'sending'}
                >
                    {status === 'sending' ? (
                        <span class="wsms-mb-btn__loading">Sending...</span>
                    ) : (
                        'Send message'
                    )}
                </button>
            </form>
        </div>
    );
}
