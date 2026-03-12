import { useState, useEffect } from 'preact/hooks';

export function useResendCooldown(initial = 0) {
    const [seconds, setSeconds] = useState(initial);

    useEffect(() => {
        if (seconds <= 0) return;
        const timer = setTimeout(() => setSeconds((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [seconds]);

    function reset(value = 60) {
        setSeconds(value);
    }

    return [seconds, reset];
}
