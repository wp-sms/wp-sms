import { useRef, useCallback } from 'preact/hooks';

/**
 * Lightweight OTP input — no external dependencies.
 * Uses wsms-vw-* CSS classes instead of Tailwind.
 */
export function OtpInputLight({ length = 6, onComplete, disabled, autoFocus = false }) {
    const inputsRef = useRef([]);
    const half = Math.ceil(length / 2);

    const focusInput = useCallback((index) => {
        const el = inputsRef.current[index];
        if (el) {
            el.focus();
            el.select();
        }
    }, []);

    const handleInput = useCallback((e, index) => {
        const value = e.target.value.replace(/\D/g, '');

        // Handle autofill: browser may deposit the full code into one input.
        if (value.length > 1) {
            const digits = value.slice(0, length);
            digits.split('').forEach((char, i) => {
                if (inputsRef.current[i]) inputsRef.current[i].value = char;
            });
            focusInput(Math.min(digits.length, length - 1));
            if (digits.length === length) {
                onComplete?.(digits);
            }
            return;
        }

        e.target.value = value;

        if (value && index < length - 1) {
            focusInput(index + 1);
        }

        // Check if all filled.
        const code = inputsRef.current.map((el) => el?.value || '').join('');
        if (code.length === length && /^\d+$/.test(code)) {
            onComplete?.(code);
        }
    }, [length, onComplete, focusInput]);

    const handleKeyDown = useCallback((e, index) => {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
            focusInput(index - 1);
        } else if (e.key === 'ArrowLeft' && index > 0) {
            e.preventDefault();
            focusInput(index - 1);
        } else if (e.key === 'ArrowRight' && index < length - 1) {
            e.preventDefault();
            focusInput(index + 1);
        }
    }, [length, focusInput]);

    const handlePaste = useCallback((e) => {
        e.preventDefault();
        const pasted = (e.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, length);

        pasted.split('').forEach((char, i) => {
            if (inputsRef.current[i]) {
                inputsRef.current[i].value = char;
            }
        });

        if (pasted.length > 0) {
            focusInput(Math.min(pasted.length, length - 1));
        }

        if (pasted.length === length) {
            onComplete?.(pasted);
        }
    }, [length, onComplete, focusInput]);

    const boxes = [];
    for (let i = 0; i < length; i++) {
        if (i === half) {
            boxes.push(<span key="sep" className="wsms-vw-otp-separator" aria-hidden="true">&ndash;</span>);
        }
        boxes.push(
            <input
                key={i}
                ref={(el) => { inputsRef.current[i] = el; }}
                type="text"
                inputMode="numeric"
                maxLength={1}
                className="wsms-vw-otp-box"
                disabled={disabled}
                autoFocus={autoFocus && i === 0}
                autoComplete="one-time-code"
                aria-label={`Digit ${i + 1} of ${length}`}
                onInput={(e) => handleInput(e, i)}
                onKeyDown={(e) => handleKeyDown(e, i)}
                onPaste={handlePaste}
            />,
        );
    }

    return <div className="wsms-vw-otp">{boxes}</div>;
}
