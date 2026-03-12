import { useState } from 'preact/hooks';
import { Mail } from 'lucide-react';
import { authError } from '../../signals/auth';
import { api } from '../../api/client';
import { extractError } from '../../utils/auth';
import { useResendCooldown } from '../../hooks/useResendCooldown';
import { Button } from '../ui/Button';

export function EmailVerifySection({ headers, className }) {
    const [resent, setResent] = useState(false);
    const [cooldown, resetCooldown] = useResendCooldown(0);

    async function handleResend() {
        if (cooldown > 0) return;
        authError.value = null;

        try {
            const res = await api.post('/auth/register/resend-email', null, headers);
            if (res.success) {
                setResent(true);
                resetCooldown(60);
            }
        } catch (err) {
            authError.value = extractError(err);
        }
    }

    return (
        <div className={`space-y-3 ${className || ''}`}>
            <div className="flex items-center gap-2 justify-center">
                <Mail className="size-4 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">
                    Check your email for a verification link
                </p>
            </div>

            {resent && (
                <p className="text-xs text-center text-green-600">Verification email resent!</p>
            )}

            <div className="flex justify-center">
                <Button variant="link" type="button" onClick={handleResend} disabled={cooldown > 0}>
                    {cooldown > 0 ? `Resend email in ${cooldown}s` : 'Resend verification email'}
                </Button>
            </div>
        </div>
    );
}
