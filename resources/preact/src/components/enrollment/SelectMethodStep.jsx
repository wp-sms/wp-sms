import { __ } from '@wordpress/i18n';
import { Button } from '../ui/Button';
import { MethodRadioCard } from './MethodRadioCard';
import { availableMethods, selectedChannel } from '../../signals/enrollment';
import { checkPasskeySupport } from '../../utils/mfa-enrollment';

const RECOMMENDED_ORDER = ['totp', 'phone', 'email', 'passkey', 'telegram', 'line'];

export function SelectMethodStep({ onSelectMethod, onConfirm, onBack }) {
    const methods = availableMethods.value;
    const selected = selectedChannel.value;
    const passkeySupport = checkPasskeySupport();

    const sorted = [...methods].sort((a, b) => {
        const ai = RECOMMENDED_ORDER.indexOf(a.id);
        const bi = RECOMMENDED_ORDER.indexOf(b.id);
        return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });

    const firstId = sorted[0]?.id;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Choose Your Method', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Select how you want to verify your identity. You can add more methods later.', 'wp-sms')}
                </p>
            </div>

            <div className="wsms-auth-stack-2" role="radiogroup" aria-label={__('Authentication methods', 'wp-sms')}>
                {sorted.map((method) => {
                    const isPasskey = method.id === 'passkey';
                    const disabled = isPasskey && !passkeySupport.supported;
                    const disabledReason = disabled
                        ? (passkeySupport.reason === 'no_https'
                            ? __('Requires secure connection (HTTPS)', 'wp-sms')
                            : __('Not supported by your browser', 'wp-sms'))
                        : undefined;

                    return (
                        <MethodRadioCard
                            key={method.id}
                            method={method}
                            selected={selected === method.id}
                            disabled={disabled}
                            disabledReason={disabledReason}
                            recommended={method.id === firstId}
                            onSelect={onSelectMethod}
                        />
                    );
                })}
            </div>

            <div className="wsms-auth-row-between">
                <Button variant="outline" onClick={onBack}>
                    {__('Back', 'wp-sms')}
                </Button>
                <Button onClick={onConfirm} disabled={!selected}>
                    {__('Continue', 'wp-sms')}
                </Button>
            </div>
        </div>
    );
}
