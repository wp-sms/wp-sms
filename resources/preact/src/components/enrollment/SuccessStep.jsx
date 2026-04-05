import { __, sprintf } from '@wordpress/i18n';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '../ui/Button';
import { selectedChannel, backupCodes } from '../../signals/enrollment';
import { enrolledFactors } from '../../signals/user';
import { getChannelMeta } from '../../utils/channel-meta';

export function SuccessStep({ onFinish }) {
    const channel = selectedChannel.value;
    const meta = getChannelMeta(channel);
    const Icon = meta.icon;

    const factors = enrolledFactors.value.filter((f) => f.channel_id !== 'backup_codes');
    const backupFactor = enrolledFactors.value.find((f) => f.channel_id === 'backup_codes');
    const backupCount = backupFactor?.remaining_codes ?? backupCodes.value?.length ?? 0;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--success">
                    <CheckCircle2 />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('MFA Setup Complete', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Your account is now protected with multi-factor authentication.', 'wp-sms')}
                </p>
            </div>

            <div className="wsms-auth-stack-2">
                {factors.map((factor) => {
                    const fMeta = getChannelMeta(factor.channel_id);
                    const FIcon = fMeta.icon;
                    return (
                        <div key={factor.channel_id} className="wsms-auth-wizard-summary-row">
                            {FIcon && <FIcon size={16} />}
                            <span className="wsms-auth-text-sm wsms-auth-font-medium">{fMeta.label}</span>
                            <CheckCircle2 size={14} className="wsms-auth-text-success" />
                        </div>
                    );
                })}
                {backupCount > 0 && (
                    <div className="wsms-auth-wizard-summary-row">
                        <span className="wsms-auth-text-sm wsms-auth-text-muted">
                            {sprintf(__('%d backup codes saved', 'wp-sms'), backupCount)}
                        </span>
                    </div>
                )}
            </div>

            <Button className="wsms-auth-full" onClick={onFinish}>
                {__('Continue to Dashboard', 'wp-sms')}
            </Button>
        </div>
    );
}
