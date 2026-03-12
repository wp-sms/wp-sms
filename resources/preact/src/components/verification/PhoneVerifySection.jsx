import { authError, authLoading } from '../../signals/auth';
import { api } from '../../api/client';
import { extractError } from '../../utils/auth';
import { useResendCooldown } from '../../hooks/useResendCooldown';
import { Button } from '../ui/Button';
import { OtpInput } from '../OtpInput';

export function PhoneVerifySection({ headers, onVerified }) {
    const [cooldown, resetCooldown] = useResendCooldown(60);

    async function handleVerify(code) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/register/verify-phone', { code }, headers);
            if (res.success) {
                onVerified?.();
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    async function handleResend() {
        if (cooldown > 0) return;
        authError.value = null;

        try {
            await api.post('/auth/register/resend-phone', null, headers);
            resetCooldown(60);
        } catch (err) {
            authError.value = extractError(err);
        }
    }

    return (
        <div className="space-y-3">
            <p className="text-sm text-muted-foreground text-center">
                Enter the 6-digit code sent to your phone
            </p>

            <OtpInput onComplete={handleVerify} disabled={authLoading.value} />

            <div className="flex justify-center">
                <Button variant="link" type="button" onClick={handleResend} disabled={cooldown > 0}>
                    {cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend code'}
                </Button>
            </div>
        </div>
    );
}
