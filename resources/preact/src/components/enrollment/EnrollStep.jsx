import { __ } from '@wordpress/i18n';
import { Button } from '../ui/Button';
import { selectedChannel, singleMethodMode, enrollmentData, backupCodes } from '../../signals/enrollment';
import { PhoneEnroll } from './channels/PhoneEnroll';
import { EmailEnroll } from './channels/EmailEnroll';
import { TotpEnroll } from './channels/TotpEnroll';
import { PasskeyEnroll } from './channels/PasskeyEnroll';
import { TelegramEnroll } from './channels/TelegramEnroll';
import { LineEnroll } from './channels/LineEnroll';

const CHANNEL_COMPONENTS = {
    phone: PhoneEnroll,
    email: EmailEnroll,
    totp: TotpEnroll,
    passkey: PasskeyEnroll,
    telegram: TelegramEnroll,
    line: LineEnroll,
};

export function EnrollStep({ onEnroll, onVerify, onPasskeyEnroll, onBack, goToStep }) {
    const channel = selectedChannel.value;
    const ChannelComponent = CHANNEL_COMPONENTS[channel];

    function handleChangeMethod() {
        enrollmentData.value = null;
        goToStep('select', 'back');
    }

    function handleEnrollmentComplete() {
        if (backupCodes.value) {
            goToStep('backup');
        } else {
            goToStep('success');
        }
    }

    if (!ChannelComponent) {
        return (
            <div className="wsms-auth-center wsms-auth-stack-4">
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Unknown authentication method.', 'wp-sms')}
                </p>
                <Button variant="outline" onClick={onBack}>
                    {__('Back', 'wp-sms')}
                </Button>
            </div>
        );
    }

    const isPolling = channel === 'telegram' || channel === 'line';
    const isPasskey = channel === 'passkey';

    return (
        <div className="wsms-auth-stack-4">
            {isPasskey ? (
                <PasskeyEnroll onPasskeyEnroll={onPasskeyEnroll} />
            ) : isPolling ? (
                <ChannelComponent onEnroll={onEnroll} onEnrollmentComplete={handleEnrollmentComplete} />
            ) : (
                <ChannelComponent onEnroll={onEnroll} onVerify={onVerify} onChangeMethod={singleMethodMode.value ? undefined : handleChangeMethod} />
            )}

            <div className={singleMethodMode.value ? 'wsms-auth-flex-center' : 'wsms-auth-row-between'}>
                <Button variant="outline" onClick={onBack}>
                    {__('Back', 'wp-sms')}
                </Button>
                {!singleMethodMode.value && (
                    <Button variant="link" size="sm" onClick={handleChangeMethod}>
                        {__('Change method', 'wp-sms')}
                    </Button>
                )}
            </div>
        </div>
    );
}
