import { useState } from 'preact/hooks';
import { Mail } from 'lucide-react';
import { cn } from '@/utils/cn';
import { authError } from '../../signals/auth';
import { methodDetails } from '../../signals/config';
import { api } from '../../api/client';
import { extractError } from '../../utils/auth';
import { useResendCooldown } from '../../hooks/useResendCooldown';
import { Button } from '../ui/Button';
import { OtpVerifyInline } from './OtpVerifyInline';

export function EmailVerifySection({ headers, className, onVerified }) {
    const emailDetails = methodDetails.value.email;
    const isOtp = emailDetails?.has_otp ?? false;
    const codeLength = emailDetails?.code_length;

    if (isOtp) {
        return (
            <OtpVerifyInline
                verifyEndpoint="/auth/register/verify/email"
                resendEndpoint="/auth/register/resend/email"
                headers={headers}
                onVerified={onVerified}
                onError={(msg) => { authError.value = msg; }}
                label="Enter the code sent to your email"
                codeLength={codeLength}
                className={className}
            />
        );
    }

    return <EmailMagicLinkSection headers={headers} className={className} />;
}

function EmailMagicLinkSection({ headers, className }) {
    const [resent, setResent] = useState(false);
    const [cooldown, resetCooldown] = useResendCooldown(0);

    async function handleResend() {
        if (cooldown > 0) return;
        authError.value = null;

        try {
            const res = await api.post('/auth/register/resend/email', null, headers);
            if (res.success) {
                setResent(true);
                resetCooldown(60);
            }
        } catch (err) {
            authError.value = extractError(err).message;
        }
    }

    return (
        <div className={cn('wsms-auth-stack-3', className)}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', justifyContent: 'center' }}>
                <Mail style={{ width: '1rem', height: '1rem', color: 'var(--muted-foreground)' }} />
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    Check your email for a verification link
                </p>
            </div>

            {resent && (
                <p className="wsms-auth-text-xs wsms-auth-center wsms-auth-text-green">Verification email resent!</p>
            )}

            <div className="wsms-auth-flex-center">
                <Button variant="link" type="button" onClick={handleResend} disabled={cooldown > 0}>
                    {cooldown > 0 ? `Resend email in ${cooldown}s` : 'Resend verification email'}
                </Button>
            </div>
        </div>
    );
}
