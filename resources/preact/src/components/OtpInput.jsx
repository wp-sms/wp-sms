import { OTPInput } from 'input-otp';
import { cn } from '@/utils/cn';

function Slot({ char, isActive, hasFakeCaret }) {
    return (
        <div
            className={cn(
                'flex size-12 items-center justify-center rounded-md border bg-transparent text-xl font-semibold transition-[border-color,box-shadow]',
                isActive
                    ? 'border-ring ring-[3px] ring-ring/50'
                    : 'border-input',
            )}
        >
            {char || (hasFakeCaret && (
                <span className="inline-block w-0.5 h-6 bg-primary animate-[wsms-blink_1s_step-end_infinite]" />
            ))}
        </div>
    );
}

export function OtpInput({ length = 6, onComplete, disabled }) {
    const half = Math.ceil(length / 2);

    return (
        <OTPInput
            maxLength={length}
            onComplete={onComplete}
            disabled={disabled}
            containerClassName="flex justify-center"
            render={({ slots }) => (
                <div className="flex items-center gap-1.5">
                    {slots.slice(0, half).map((slot, i) => (
                        <Slot key={i} {...slot} />
                    ))}
                    <span className="text-lg text-muted-foreground mx-0.5">&ndash;</span>
                    {slots.slice(half).map((slot, i) => (
                        <Slot key={i + half} {...slot} />
                    ))}
                </div>
            )}
        />
    );
}
