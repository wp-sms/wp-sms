import { useRef, useEffect } from 'preact/hooks';

export function StepTransition({ step, direction = 'forward', children }) {
    const prevStep = useRef(step);
    const containerRef = useRef(null);

    // Compute animation class from render-time comparison (read-only, no mutation)
    const stepChanged = step !== prevStep.current;
    const animClass = stepChanged
        ? (direction === 'back' ? 'wsms-auth-step-enter--back' : 'wsms-auth-step-enter--forward')
        : '';

    // Update ref and move focus inside the effect (not during render)
    useEffect(() => {
        if (step === prevStep.current || !containerRef.current) return;
        prevStep.current = step;
        requestAnimationFrame(() => {
            const target = containerRef.current?.querySelector('h1, h2, h3, input, button, [tabindex]:not([tabindex="-1"])');
            target?.focus();
        });
    }, [step]);

    return (
        <div ref={containerRef} className={`wsms-auth-step-transition ${animClass}`} key={step}>
            {children}
        </div>
    );
}
