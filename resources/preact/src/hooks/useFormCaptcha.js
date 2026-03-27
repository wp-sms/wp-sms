import { useState, useCallback, useRef } from 'preact/hooks';

/**
 * Lightweight CAPTCHA hook for standalone forms (subscription, messaging button).
 * Unlike useCaptcha(), this doesn't depend on the auth config signal —
 * it takes the captcha config object directly from inline window config.
 */
export function useFormCaptcha(captchaConfig, action) {
    const [token, setToken] = useState(null);
    const resetRef = useRef(null);

    const enabled = !!(captchaConfig?.enabled && captchaConfig?.protected_actions?.includes(action));

    const getHeaders = useCallback(() => {
        if (!token) return {};
        return { 'X-Captcha-Response': token };
    }, [token]);

    const reset = useCallback(() => {
        setToken(null);
        resetRef.current?.();
    }, []);

    return {
        token,
        setToken,
        resetRef,
        enabled,
        reset,
        getHeaders,
        provider: captchaConfig?.provider ?? null,
        siteKey: captchaConfig?.site_key ?? null,
    };
}
