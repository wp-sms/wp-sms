import { useState } from 'preact/hooks';
import { OTPInput } from 'input-otp';
import { __ } from '@wordpress/i18n';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { normalizeDigits, extractDigits, REGEXP_UNICODE_DIGITS } from '../utils/digits';

function Slot({ char, isActive, hasFakeCaret }) {
    return (
        <div className={`wsms-auth-otp-slot${isActive ? ' wsms-auth-otp-slot--active' : ''}`}>
            {char || (hasFakeCaret && (
                <span className="wsms-auth-otp-caret" />
            ))}
        </div>
    );
}

function WidgetSlot({ char, isActive, hasFakeCaret }) {
    return (
        <div className={`wsms-vw-otp-slot${isActive ? ' wsms-vw-otp-slot--active' : ''}`}>
            {char || (hasFakeCaret && (
                <span className="wsms-vw-otp-caret" />
            ))}
        </div>
    );
}

function SubscriptionSlot({ char, isActive, hasFakeCaret }) {
    return (
        <div className={`wsms-sub-form__otp-slot${isActive ? ' wsms-sub-form__otp-slot--active' : ''}`}>
            {char || (hasFakeCaret && (
                <span className="wsms-sub-form__otp-caret" />
            ))}
        </div>
    );
}

const VARIANT_CONFIG = {
    default: { Slot, container: 'wsms-auth-otp', slots: 'wsms-auth-otp-slots', separator: 'wsms-auth-otp-separator' },
    widget: { Slot: WidgetSlot, container: 'wsms-vw-otp', slots: 'wsms-vw-otp-slots', separator: 'wsms-vw-otp-separator' },
    subscription: { Slot: SubscriptionSlot, container: 'wsms-sub-form__otp', slots: 'wsms-sub-form__otp-slots', separator: 'wsms-sub-form__otp-separator' },
};

export function OtpInput({ length = 6, onComplete, disabled, autoFocus = false, variant = 'default' }) {
    const focusRef = useAutoFocus(autoFocus);
    const [value, setValue] = useState('');
    const half = Math.ceil(length / 2);
    const cfg = VARIANT_CONFIG[variant] || VARIANT_CONFIG.default;
    const SlotComponent = cfg.Slot;

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
                        <SlotComponent key={i} {...slot} />
                    ))}
                    <span className={cfg.separator} aria-hidden="true">&ndash;</span>
                    {slots.slice(half).map((slot, i) => (
                        <SlotComponent key={i + half} {...slot} />
                    ))}
                </div>
            )}
        />
    );
}
