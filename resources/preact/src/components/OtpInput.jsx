import { OTPInput, REGEXP_ONLY_DIGITS } from 'input-otp';
import { useAutoFocus } from '../hooks/useAutoFocus';

function Slot({ char, isActive, hasFakeCaret }) {
    return (
        <div
            className={`flex size-12 items-center justify-center rounded-md border bg-transparent text-xl font-semibold transition-[border-color,box-shadow] ${isActive ? 'border-ring ring-[3px] ring-ring/50' : 'border-input'}`}
        >
            {char || (hasFakeCaret && (
                <span className="inline-block w-0.5 h-6 bg-primary animate-[wsms-blink_1s_step-end_infinite]" />
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
    default: { Slot, container: 'flex justify-center', slots: 'flex items-center gap-1.5', separator: 'text-lg text-muted-foreground mx-0.5' },
    widget: { Slot: WidgetSlot, container: 'wsms-vw-otp', slots: 'wsms-vw-otp-slots', separator: 'wsms-vw-otp-separator' },
    subscription: { Slot: SubscriptionSlot, container: 'wsms-sub-form__otp', slots: 'wsms-sub-form__otp-slots', separator: 'wsms-sub-form__otp-separator' },
};

const stripNonDigits = (text) => text.replace(/\D/g, '');

export function OtpInput({ length = 6, onComplete, disabled, autoFocus = false, variant = 'default' }) {
    const focusRef = useAutoFocus(autoFocus);
    const half = Math.ceil(length / 2);
    const cfg = VARIANT_CONFIG[variant] || VARIANT_CONFIG.default;
    const SlotComponent = cfg.Slot;

    return (
        <OTPInput
            ref={focusRef}
            maxLength={length}
            onComplete={onComplete}
            disabled={disabled}
            autoComplete="one-time-code"
            inputMode="numeric"
            pattern={REGEXP_ONLY_DIGITS}
            pushPasswordManagerStrategy="increase-width"
            pasteTransformer={stripNonDigits}
            containerClassName={cfg.container}
            render={({ slots }) => (
                <div className={cfg.slots}>
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
