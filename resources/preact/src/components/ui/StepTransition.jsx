import { useRef } from 'preact/hooks';

export function StepTransition({ step, direction = 'forward', children }) {
    const prevStep = useRef(step);
    let animClass = '';

    if (step !== prevStep.current) {
        animClass = direction === 'back' ? 'wsms-auth-step-enter--back' : 'wsms-auth-step-enter--forward';
        prevStep.current = step;
    }

    return (
        <div className={`wsms-auth-step-transition ${animClass}`} key={step}>
            {children}
        </div>
    );
}
