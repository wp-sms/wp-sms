import { useState } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { ShieldCheck, Clock, AlertTriangle, ShieldAlert, X } from 'lucide-react';
import { Button } from '../ui/Button';
import { Dialog } from '../ui/Dialog';
import { wizardMode, gracePeriodInfo } from '../../signals/enrollment';

export function WelcomeStep({ onStartSetup, onSkip, onDismissPrompt, onSignOut, backupCodeInfo }) {
    const mode = wizardMode.value;

    if (mode === 'grace') {
        return <GraceWelcome onStartSetup={onStartSetup} onSkip={onSkip} />;
    }

    if (mode === 'voluntary') {
        return <VoluntaryWelcome onStartSetup={onStartSetup} onDismissPrompt={onDismissPrompt} onSkip={onSkip} />;
    }

    if (mode === 're-enroll') {
        return <ReEnrollWelcome onStartSetup={onStartSetup} onSkip={onSkip} backupCodeInfo={backupCodeInfo} />;
    }

    // Default: forced
    return <ForcedWelcome onStartSetup={onStartSetup} />;
}

function ForcedWelcome({ onStartSetup }) {
    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <ShieldCheck />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Set Up Multi-Factor Authentication', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Your administrator requires MFA for your account. Add a second authentication method to keep your account secure.', 'wp-sms')}
                </p>
            </div>
            <Button className="wsms-auth-full" onClick={onStartSetup}>
                {__('Get Started', 'wp-sms')}
            </Button>
        </div>
    );
}

function GraceWelcome({ onStartSetup, onSkip }) {
    const info = gracePeriodInfo.value;
    const days = info?.remaining_days ?? 0;
    const hours = info?.remaining_hours ?? 0;

    // C5: Grace expired
    if (days <= 0 && hours <= 0) {
        return (
            <div className="wsms-auth-stack-4">
                <div className="wsms-auth-flex-center">
                    <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--destructive">
                        <ShieldAlert />
                    </div>
                </div>
                <div className="wsms-auth-center wsms-auth-stack-2">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                        {__('Grace Period Ended', 'wp-sms')}
                    </h2>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Multi-factor authentication is now required. Set up MFA to continue using your account.', 'wp-sms')}
                    </p>
                </div>
                <Button className="wsms-auth-full" onClick={onStartSetup}>
                    {__('Set Up MFA Now', 'wp-sms')}
                </Button>
            </div>
        );
    }

    // C4: Less than 24 hours — no skip
    if (days === 0 && hours > 0) {
        return (
            <div className="wsms-auth-stack-4">
                <div className="wsms-auth-flex-center">
                    <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--destructive">
                        <Clock />
                    </div>
                </div>
                <div className="wsms-auth-center wsms-auth-stack-2">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                        {__('MFA Required Today', 'wp-sms')}
                    </h2>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {sprintf(__('~%d hours remaining to set up multi-factor authentication.', 'wp-sms'), hours)}
                    </p>
                </div>
                <Button className="wsms-auth-full" onClick={onStartSetup}>
                    {__('Set Up MFA Now', 'wp-sms')}
                </Button>
            </div>
        );
    }

    // C3: 1 day left
    if (days === 1) {
        return (
            <div className="wsms-auth-stack-4">
                <div className="wsms-auth-flex-center">
                    <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--warning">
                        <Clock />
                    </div>
                </div>
                <div className="wsms-auth-center wsms-auth-stack-2">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                        {__('MFA Required Tomorrow', 'wp-sms')}
                    </h2>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('You have 1 day left to set up multi-factor authentication before it becomes mandatory.', 'wp-sms')}
                    </p>
                </div>
                <Button className="wsms-auth-full" onClick={onStartSetup}>
                    {__('Set Up MFA Now', 'wp-sms')}
                </Button>
                <div className="wsms-auth-center">
                    <button type="button" className="wsms-auth-btn wsms-auth-btn--link wsms-auth-text-xs wsms-auth-text-muted" onClick={onSkip}>
                        {__('Skip (1 day left)', 'wp-sms')}
                    </button>
                </div>
            </div>
        );
    }

    // C2: 2-4 days left
    if (days >= 2 && days <= 4) {
        return (
            <div className="wsms-auth-stack-4">
                <div className="wsms-auth-flex-center">
                    <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--warning">
                        <Clock />
                    </div>
                </div>
                <div className="wsms-auth-center wsms-auth-stack-2">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                        {sprintf(__('MFA Required in %d Days', 'wp-sms'), days)}
                    </h2>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Multi-factor authentication will soon be required for your account. Set it up now to avoid interruption.', 'wp-sms')}
                    </p>
                </div>
                <Button className="wsms-auth-full" onClick={onStartSetup}>
                    {__('Set Up MFA Now', 'wp-sms')}
                </Button>
                <Button variant="outline" className="wsms-auth-full" onClick={onSkip}>
                    {sprintf(__('Skip for Now (%d days left)', 'wp-sms'), days)}
                </Button>
            </div>
        );
    }

    // C1: 5+ days
    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <ShieldCheck />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Set Up MFA Soon', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {sprintf(__('Multi-factor authentication will be required in %d days. Set it up now for a smooth transition.', 'wp-sms'), days)}
                </p>
            </div>
            <Button className="wsms-auth-full" onClick={onStartSetup}>
                {__('Set Up MFA Now', 'wp-sms')}
            </Button>
            <Button variant="outline" className="wsms-auth-full" onClick={onSkip}>
                {__('Remind Me Later', 'wp-sms')}
            </Button>
        </div>
    );
}

function VoluntaryWelcome({ onStartSetup, onDismissPrompt, onSkip }) {
    const [showDismissConfirm, setShowDismissConfirm] = useState(false);

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-row-between">
                <div />
                <button
                    type="button"
                    className="wsms-auth-btn wsms-auth-btn--ghost wsms-auth-btn--icon"
                    onClick={onSkip}
                    aria-label={__('Close', 'wp-sms')}
                >
                    <X size={16} />
                </button>
            </div>
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon">
                    <ShieldCheck />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Secure Your Account with MFA', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('Multi-factor authentication adds an extra layer of security. Even if someone gets your password, they can\'t access your account.', 'wp-sms')}
                </p>
            </div>
            <ul className="wsms-auth-stack-2 wsms-auth-text-sm">
                <li>{__('Protects against password theft', 'wp-sms')}</li>
                <li>{__('Prevents unauthorized access', 'wp-sms')}</li>
                <li>{__('Multiple methods available', 'wp-sms')}</li>
            </ul>
            <Button className="wsms-auth-full" onClick={onStartSetup}>
                {__('Set Up MFA', 'wp-sms')}
            </Button>
            <div className="wsms-auth-center">
                <button type="button" className="wsms-auth-btn wsms-auth-btn--link wsms-auth-text-xs wsms-auth-text-muted" onClick={() => setShowDismissConfirm(true)}>
                    {__("Don't show again", 'wp-sms')}
                </button>
            </div>

            {showDismissConfirm && (
                <Dialog open onClose={() => setShowDismissConfirm(false)}>
                    <div className="wsms-auth-stack-4" style={{ padding: '1.5rem' }}>
                        <h3 className="wsms-auth-text-lg wsms-auth-font-semibold">
                            {__('Are you sure?', 'wp-sms')}
                        </h3>
                        <p className="wsms-auth-text-sm wsms-auth-text-muted">
                            {__('You can enable MFA anytime from Security settings.', 'wp-sms')}
                        </p>
                        <div className="wsms-auth-stack-2">
                            <Button variant="outline" className="wsms-auth-full" onClick={() => setShowDismissConfirm(false)}>
                                {__('Keep Showing', 'wp-sms')}
                            </Button>
                            <Button variant="ghost" className="wsms-auth-full" onClick={onDismissPrompt}>
                                {__("Yes, Don't Show Again", 'wp-sms')}
                            </Button>
                        </div>
                    </div>
                </Dialog>
            )}
        </div>
    );
}

function ReEnrollWelcome({ onStartSetup, onSkip, backupCodeInfo }) {
    const remaining = backupCodeInfo?.remaining_codes ?? 0;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-flex-center">
                <div className="wsms-auth-wizard-icon wsms-auth-wizard-icon--warning">
                    <AlertTriangle />
                </div>
            </div>
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Set Up a New MFA Method', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__('You signed in with a backup code. Set up a new authentication method to keep your account secure.', 'wp-sms')}
                </p>
                {remaining > 0 && (
                    <p className={`wsms-auth-text-sm ${remaining <= 3 ? 'wsms-auth-text-destructive' : 'wsms-auth-text-muted'}`}>
                        {sprintf(__('%d backup codes remaining', 'wp-sms'), remaining)}
                    </p>
                )}
            </div>
            <Button className="wsms-auth-full" onClick={onStartSetup}>
                {__('Set Up New Method', 'wp-sms')}
            </Button>
            <Button variant="outline" className="wsms-auth-full" onClick={onSkip}>
                {__("I'll Do It Later", 'wp-sms')}
            </Button>
        </div>
    );
}
