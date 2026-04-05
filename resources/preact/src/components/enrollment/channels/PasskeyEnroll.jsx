import { __ } from '@wordpress/i18n';
import { Fingerprint } from 'lucide-react';
import { Button } from '../../ui/Button';
import { Alert } from '../../ui/Alert';
import { wizardError, wizardLoading } from '../../../signals/enrollment';

export function PasskeyEnroll({ onPasskeyEnroll }) {
    const loading = wizardLoading.value;
    const error = wizardError.value;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <Fingerprint />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Passkey Setup', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Use your fingerprint, face, or security key for fast, secure verification.', 'wp-sms')}
                </p>
            </div>

            {error && (
                <Alert variant="destructive" onDismiss={() => { wizardError.value = null; }}>
                    {error.message}
                </Alert>
            )}

            <div className="wsms-auth-flex-center">
                <div className={`wsms-auth-wizard-waiting-box ${loading ? 'wsms-auth-wizard-waiting-box--active' : ''}`}>
                    <Fingerprint className={loading ? 'wsms-auth-passkey-icon--prompting' : ''} style={{ width: '3rem', height: '3rem' }} />
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {loading
                            ? __('Follow your browser\'s prompts to register your passkey...', 'wp-sms')
                            : __('Click the button below to start passkey registration.', 'wp-sms')}
                    </p>
                </div>
            </div>

            <Button className="wsms-auth-full" onClick={onPasskeyEnroll} disabled={loading}>
                {loading ? __('Waiting for browser...', 'wp-sms') : __('Register Passkey', 'wp-sms')}
            </Button>
        </div>
    );
}
