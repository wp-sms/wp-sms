import { __ } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';
import { Button } from '../ui/Button';

export function UnsupportedStep({ onSignOut }) {
    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--warning">
                    <AlertTriangle />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Unable to Set Up MFA', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Your browser does not support the only available authentication method (Passkey). Please use a different browser or device, or contact your administrator.', 'wp-sms')}
                </p>
            </div>
            <div className="wsms-auth-stack-2">
                <Button variant="outline" className="wsms-auth-full" onClick={() => window.location.href = 'mailto:admin'}>
                    {__('Contact Administrator', 'wp-sms')}
                </Button>
                <Button variant="ghost" className="wsms-auth-full" onClick={onSignOut}>
                    {__('Sign Out', 'wp-sms')}
                </Button>
            </div>
        </div>
    );
}
