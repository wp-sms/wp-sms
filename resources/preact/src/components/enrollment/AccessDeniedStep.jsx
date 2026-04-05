import { __ } from '@wordpress/i18n';
import { ShieldX } from 'lucide-react';
import { Button } from '../ui/Button';

export function AccessDeniedStep({ onSignOut }) {
    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--destructive">
                    <ShieldX />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Access Denied', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Multi-factor authentication is required for your account, but you do not have permission to set it up. Please contact your administrator.', 'wp-sms')}
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
