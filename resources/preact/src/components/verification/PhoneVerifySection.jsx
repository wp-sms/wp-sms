import { __ } from '@wordpress/i18n';
import { authError } from '../../signals/auth';
import { methodDetails } from '../../signals/config';
import { OtpVerifyInline } from './OtpVerifyInline';

export function PhoneVerifySection({ headers, onVerified }) {
    const codeLength = methodDetails.value.phone?.code_length;

    return (
        <OtpVerifyInline
            verifyEndpoint="/auth/register/verify/phone"
            resendEndpoint="/auth/register/resend/phone"
            headers={headers}
            onVerified={onVerified}
            onError={(msg) => { authError.value = msg; }}
            label={__('Enter the code sent to your phone', 'wp-sms')}
            codeLength={codeLength}
            initialCooldown={60}
        />
    );
}
