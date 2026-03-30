import { useState, useEffect, useRef } from 'preact/hooks';
import { OTPInput } from 'input-otp';
import { __ } from '@wordpress/i18n';
import { cn } from '../utils/cn';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { normalizeDigits, extractDigits, REGEXP_UNICODE_DIGITS } from '../utils/digits';

function Slot({ char, isActive, hasFakeCaret, error, prefix }) {
    return (
        <div className={cn(`${prefix}-slot`, isActive && `${prefix}-slot--active`, error && `${prefix}-slot--error`)}>
            {char || (hasFakeCaret && (
                <span className={`${prefix}-caret`} />
            ))}
        </div>
    );
}

const VARIANT_CONFIG = {
    default: { prefix: 'wsms-auth-otp', container: 'wsms-auth-otp', slots: 'wsms-auth-otp-slots', separator: 'wsms-auth-otp-separator' },
    widget: { prefix: 'wsms-vw-otp', container: 'wsms-vw-otp', slots: 'wsms-vw-otp-slots', separator: 'wsms-vw-otp-separator' },
    subscription: { prefix: 'wsms-sub-form__otp', container: 'wsms-sub-form__otp', slots: 'wsms-sub-form__otp-slots', separator: 'wsms-sub-form__otp-separator' },
};

export function OtpInput({ length = 6, onComplete, disabled, autoFocus = false, variant = 'default', error = false }) {
    const focusRef = useAutoFocus(autoFocus);
    const [value, setValue] = useState('');
    const prevErrorRef = useRef(false);

    useEffect(() => {
        if (error && !prevErrorRef.current) {
            setValue('');
        }
        prevErrorRef.current = error;
    }, [error]);

    const half = Math.ceil(length / 2);
    const cfg = VARIANT_CONFIG[variant] || VARIANT_CONFIG.default;

    const handleChange = (newValue) => {
        setValue(normalizeDigits(newValue));
    };

    return (
        <OTPInput
            ref={focusRef}
            maxLength={length}
            value={value}
            onChange={handleChange}
            onComplete={onComplete}
            disabled={disabled}
            autoComplete="one-time-code"
            inputMode="numeric"
            pattern={REGEXP_UNICODE_DIGITS}
            pushPasswordManagerStrategy="increase-width"
            pasteTransformer={extractDigits}
            containerClassName={cfg.container}
            aria-label={__('Verification code', 'wp-sms')}
            render={({ slots }) => (
                <div className={cfg.slots} dir="ltr">
                    {slots.slice(0, half).map((slot, i) => (
                        <Slot key={i} {...slot} error={error} prefix={cfg.prefix} />
                    ))}
                    <span className={cfg.separator} aria-hidden="true">&ndash;</span>
                    {slots.slice(half).map((slot, i) => (
                        <Slot key={i + half} {...slot} error={error} prefix={cfg.prefix} />
                    ))}
                </div>
            )}
        />
    );
}
