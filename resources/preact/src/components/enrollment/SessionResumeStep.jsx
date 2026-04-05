import { __ } from '@wordpress/i18n';
import { RotateCcw } from 'lucide-react';
import { Button } from '../ui/Button';
import { getChannelMeta } from '../../utils/channel-meta';

export function SessionResumeStep({ resumeChannel, onContinue, onStartOver }) {
    const meta = resumeChannel ? getChannelMeta(resumeChannel) : null;
    const Icon = meta?.icon;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <RotateCcw />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Welcome Back', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('You have an enrollment in progress. Would you like to continue where you left off?', 'wp-sms')}
                </p>
            </div>

            {meta && (
                <div className="wsms-auth-wizard-summary-row wsms-auth-flex-center">
                    {Icon && <Icon size={16} />}
                    <span className="wsms-auth-text-sm wsms-auth-font-medium">{meta.label}</span>
                </div>
            )}

            <div className="wsms-auth-stack-2">
                <Button className="wsms-auth-full" onClick={onContinue}>
                    {__('Continue Setup', 'wp-sms')}
                </Button>
                <Button variant="outline" className="wsms-auth-full" onClick={onStartOver}>
                    {__('Start Over', 'wp-sms')}
                </Button>
            </div>
        </div>
    );
}
