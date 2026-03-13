import { useEffect, useRef, useCallback } from 'preact/hooks';

const POLL_INTERVAL = 200;
const MAX_POLLS = 50; // 10 seconds max wait

/**
 * Renders a CAPTCHA widget for the configured provider.
 *
 * Props:
 *  - provider: 'turnstile' | 'recaptcha' | 'hcaptcha'
 *  - siteKey: the public site key
 *  - onVerify: callback with the token string
 *  - resetRef: ref to store the reset function
 */
export function CaptchaWidget({ provider, siteKey, onVerify, resetRef }) {
    const containerRef = useRef(null);
    const widgetId = useRef(null);

    const getApi = useCallback(() => {
        switch (provider) {
            case 'turnstile':
                return window.turnstile;
            case 'recaptcha':
                return window.grecaptcha;
            case 'hcaptcha':
                return window.hcaptcha;
            default:
                return null;
        }
    }, [provider]);

    useEffect(() => {
        if (!siteKey || !provider) return;

        let pollCount = 0;
        let pollTimer = null;
        let mounted = true;

        function renderWidget() {
            const api = getApi();
            if (!api || !containerRef.current || !mounted) return;

            // Clear previous widget.
            if (widgetId.current !== null) {
                try {
                    if (provider === 'turnstile') api.remove(widgetId.current);
                    else api.reset(widgetId.current);
                } catch {
                    // ignore
                }
                containerRef.current.innerHTML = '';
            }

            const opts = {
                sitekey: siteKey,
                callback: (token) => {
                    if (mounted) onVerify(token);
                },
            };

            const renderOpts = provider === 'recaptcha'
                ? { ...opts, size: 'normal' }
                : opts;
            widgetId.current = api.render(containerRef.current, renderOpts);
        }

        function poll() {
            if (!mounted) return;
            if (getApi()) {
                renderWidget();
            } else if (pollCount < MAX_POLLS) {
                pollCount++;
                pollTimer = setTimeout(poll, POLL_INTERVAL);
            }
        }

        poll();

        // Expose reset function.
        if (resetRef) {
            resetRef.current = () => {
                const api = getApi();
                if (api && widgetId.current !== null) {
                    try {
                        api.reset(widgetId.current);
                    } catch {
                        // ignore
                    }
                }
            };
        }

        return () => {
            mounted = false;
            if (pollTimer) clearTimeout(pollTimer);
            const api = getApi();
            if (api && widgetId.current !== null) {
                try {
                    if (provider === 'turnstile') api.remove(widgetId.current);
                } catch {
                    // ignore
                }
            }
            widgetId.current = null;
        };
    }, [provider, siteKey]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!siteKey || !provider) return null;

    return (
        <div className="flex justify-center">
            <div ref={containerRef} />
        </div>
    );
}
