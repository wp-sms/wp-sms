import { useState, useCallback, useRef } from 'preact/hooks';
import { captchaConfig } from '../signals/config';

/**
 * Hook for managing CAPTCHA state.
 *
 * Returns token tracking and helpers to check whether CAPTCHA is required
 * for a given action, and to pass the token as a request header.
 */
export function useCaptcha() {
    const [token, setToken] = useState(null);
    const resetRef = useRef(null);

    const captcha = captchaConfig.value;
    const enabled = captcha?.enabled ?? false;

    const isRequiredFor = useCallback(
        (action) => {
            if (!enabled) return false;
            const actions = captcha?.protected_actions ?? [];
            return actions.includes(action);
        },
        [enabled, captcha],
    );

    const getHeaders = useCallback(() => {
        if (!token) return {};
        return { 'X-Captcha-Response': token };
    }, [token]);

    const reset = useCallback(() => {
        setToken(null);
        if (resetRef.current) {
            resetRef.current();
        }
    }, []);

    return {
        token,
        setToken,
        reset,
        resetRef,
        isRequiredFor,
        getHeaders,
        enabled,
        provider: captcha?.provider ?? null,
        siteKey: captcha?.site_key ?? null,
    };
}
