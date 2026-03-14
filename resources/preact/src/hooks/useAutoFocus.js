import { useRef, useEffect } from 'preact/hooks';

/**
 * Reliable focus hook for SPA transitions where HTML autoFocus is unreliable.
 * Uses requestAnimationFrame to wait for the element to be painted before focusing.
 *
 * @param {boolean} [shouldFocus=true] - Whether to focus the element
 * @returns {import('preact').RefObject<HTMLElement>}
 */
export function useAutoFocus(shouldFocus = true) {
    const ref = useRef(null);

    useEffect(() => {
        if (!shouldFocus || !ref.current) return;
        const id = requestAnimationFrame(() => ref.current?.focus());
        return () => cancelAnimationFrame(id);
    }, [shouldFocus]);

    return ref;
}
