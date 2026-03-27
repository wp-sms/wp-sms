import { useRef, useEffect } from '@wordpress/element';

export function VerifyWidgetAdapter({ channel, identifier, onVerified }) {
    const containerRef = useRef(null);
    const onVerifiedRef = useRef(onVerified);
    onVerifiedRef.current = onVerified;

    useEffect(() => {
        const el = containerRef.current;
        if (!el || !identifier || !window.wsmsVerify) return;

        wsmsVerify.mount(el, {
            channel,
            identifier,
            onVerified: (token) => onVerifiedRef.current?.(token),
        });

        return () => wsmsVerify.destroy(el);
    }, [channel, identifier]);

    return wp.element.createElement('div', { ref: containerRef });
}
