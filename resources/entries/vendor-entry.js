import * as preact from 'preact';
import * as preactHooks from 'preact/hooks';
import * as preactCompat from 'preact/compat';
import * as signals from '@preact/signals';
import { PhoneInput } from 'lite-phone-input/vanilla';
import { OTPInput, REGEXP_ONLY_DIGITS } from 'input-otp';
import 'lite-phone-input/styles';

window.WsmsVendor = {
    preact,
    preactHooks,
    preactCompat,
    signals,
    LitePhoneInput: { PhoneInput },
    inputOtp: { OTPInput, REGEXP_ONLY_DIGITS },
};
