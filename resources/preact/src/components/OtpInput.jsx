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

const stripNonDigits = (text) => text.replace(/\D/g, '');

export function OtpInput({ length = 6, onComplete, disabled, autoFocus = false, variant = 'default' }) {
    const focusRef = useAutoFocus(autoFocus);
    const half = Math.ceil(length / 2);
    const isWidget = variant === 'widget';
    const SlotComponent = isWidget ? WidgetSlot : Slot;

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
            containerClassName={isWidget ? 'wsms-vw-otp' : 'flex justify-center'}
            render={({ slots }) => (
                <div className={isWidget ? 'wsms-vw-otp-slots' : 'flex items-center gap-1.5'}>
                    {slots.slice(0, half).map((slot, i) => (
                        <SlotComponent key={i} {...slot} />
                    ))}
                    <span className={isWidget ? 'wsms-vw-otp-separator' : 'text-lg text-muted-foreground mx-0.5'} aria-hidden="true">&ndash;</span>
                    {slots.slice(half).map((slot, i) => (
                        <SlotComponent key={i + half} {...slot} />
                    ))}
                </div>
            )}
        />
    );
}
