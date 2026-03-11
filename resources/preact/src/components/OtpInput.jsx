import { OTPInput } from 'input-otp';

function Slot({ char, isActive, hasFakeCaret }) {
    return (
        <div class={`wsms-otp-slot ${isActive ? 'is-active' : ''}`}>
            {char || (hasFakeCaret && <span class="wsms-otp-caret" />)}
        </div>
    );
}

export function OtpInput({ length = 6, onComplete, disabled }) {
    return (
        <OTPInput
            maxLength={length}
            onComplete={onComplete}
            disabled={disabled}
            containerClassName="wsms-otp-container"
            render={({ slots }) => (
                <div class="wsms-otp-group">
                    {slots.slice(0, 3).map((slot, i) => (
                        <Slot key={i} {...slot} />
                    ))}
                    <span class="wsms-otp-separator">&ndash;</span>
                    {slots.slice(3).map((slot, i) => (
                        <Slot key={i + 3} {...slot} />
                    ))}
                </div>
            )}
        />
    );
}
