import { authError } from '../../signals/auth';
import { methodDetails } from '../../signals/config';
import { OtpVerifyInline } from './OtpVerifyInline';

export function PhoneVerifySection({ headers, onVerified }) {
    const codeLength = methodDetails.value.phone?.code_length;

    return (
        <OtpVerifyInline
            verifyEndpoint="/auth/register/verify-phone"
            resendEndpoint="/auth/register/resend-phone"
            headers={headers}
            onVerified={onVerified}
            onError={(msg) => { authError.value = msg; }}
            label="Enter the code sent to your phone"
            codeLength={codeLength}
            initialCooldown={60}
        />
    );
}
