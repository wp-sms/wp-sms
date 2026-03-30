import { useState } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@/utils/cn';
import { api } from '../../api/client';
import { extractError } from '../../utils/auth';
import { useResendCooldown } from '../../hooks/useResendCooldown';
import { Button } from '../ui/Button';
import { OtpInput } from '../OtpInput';

/**
 * Shared inline OTP verification UI.
 *
 * @param {string}   verifyEndpoint   - API path to POST the code (e.g. '/auth/profile/verify/email')
 * @param {string}   [resendEndpoint] - API path to POST for resend (omit to hide resend button)
 * @param {object}   [headers]        - Extra request headers (e.g. challenge token)
 * @param {function} onVerified       - Called on successful verification
 * @param {function} onError          - Called with error message string
 * @param {string}   label            - Prompt text (e.g. 'Enter the code sent to your email')
 * @param {string}   [className]      - Additional CSS classes on the wrapper
 * @param {number}   [initialCooldown=0] - Initial resend cooldown in seconds
 * @param {number}   [codeLength]        - OTP digit count (defaults to config or 6)
 */
export function OtpVerifyInline({ verifyEndpoint, resendEndpoint, headers, onVerified, onError, label, className, initialCooldown = 0, codeLength, autoFocus = true }) {
    const [verifying, setVerifying] = useState(false);
    const [hasError, setHasError] = useState(false);
    const [cooldown, resetCooldown] = useResendCooldown(initialCooldown);

    async function handleVerify(code) {
        setVerifying(true);
        setHasError(false);
        try {
            const res = await api.post(verifyEndpoint, { code }, headers);
            if (res.success) {
                onVerified?.();
            }
        } catch (err) {
            setHasError(true);
            onError?.(extractError(err).message);
        } finally {
            setVerifying(false);
        }
    }

    async function handleResend() {
        if (cooldown > 0 || !resendEndpoint) return;
        try {
            await api.post(resendEndpoint, null, headers);
            resetCooldown(60);
        } catch (err) {
            onError?.(extractError(err).message);
        }
    }

    return (
        <div className={cn('wsms-auth-stack-3', className)}>
            <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">{label}</p>

            <OtpInput length={codeLength} onComplete={handleVerify} disabled={verifying} autoFocus={autoFocus} error={hasError} />

            {resendEndpoint && (
                <div className="wsms-auth-flex-center">
                    <Button variant="link" type="button" onClick={handleResend} disabled={cooldown > 0}>
                        {cooldown > 0 ? sprintf(__('Resend in %ds', 'wp-sms'), cooldown) : __('Resend code', 'wp-sms')}
                    </Button>
                </div>
            )}
        </div>
    );
}
