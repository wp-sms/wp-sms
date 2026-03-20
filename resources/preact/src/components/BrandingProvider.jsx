import { useEffect, useRef } from 'preact/hooks';
import { brandingConfig, cssPalette, isPreviewMode } from '../signals/branding';
import { authUrl } from '../utils/urls';

/**
 * Applies branding CSS variables to :root and manages preview-mode postMessage.
 * Wrap the app with this component.
 */
export function BrandingProvider({ children }) {
    const appliedKeysRef = useRef([]);

    // Apply CSS variables whenever palette changes
    useEffect(() => {
        const vars = cssPalette.value;
        const root = document.documentElement;
        const newKeys = Object.keys(vars);

        // Remove vars that were in the old palette but not the new one
        for (const key of appliedKeysRef.current) {
            if (!(key in vars)) {
                root.style.removeProperty(key);
            }
        }

        for (const [key, value] of Object.entries(vars)) {
            root.style.setProperty(key, value);
        }

        appliedKeysRef.current = newKeys;

        return () => {
            for (const key of appliedKeysRef.current) {
                root.style.removeProperty(key);
            }
            appliedKeysRef.current = [];
        };
    }, [cssPalette.value]);

    // Apply background image on body
    useEffect(() => {
        const config = brandingConfig.value;
        const layout = config?.layout ?? 'centered';

        if (layout === 'centered' && config?.background_image_url) {
            document.body.style.backgroundImage = `url(${config.background_image_url})`;
            document.body.style.backgroundSize = 'cover';
            document.body.style.backgroundPosition = 'center';
            document.body.style.backgroundRepeat = 'no-repeat';
        } else {
            document.body.style.removeProperty('background-image');
            document.body.style.removeProperty('background-size');
            document.body.style.removeProperty('background-position');
            document.body.style.removeProperty('background-repeat');
        }
    }, [brandingConfig.value?.background_image_url, brandingConfig.value?.layout]);

    // Load Google Font dynamically in preview mode
    useEffect(() => {
        const config = brandingConfig.value;
        if (!config?.google_font || !config?.font_family || config.font_family === 'system-ui') return;

        const existing = document.getElementById('wsms-branding-google-font');
        if (existing) existing.remove();

        const link = document.createElement('link');
        link.id = 'wsms-branding-google-font';
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(config.font_family)}:wght@400;500;600;700&display=swap`;
        document.head.appendChild(link);

        return () => link.remove();
    }, [brandingConfig.value?.font_family, brandingConfig.value?.google_font]);

    // Listen for preview postMessage
    useEffect(() => {
        if (!isPreviewMode.value) return;

        function handleMessage(e) {
            if (e.data?.type === 'wsms-branding-preview') {
                brandingConfig.value = e.data.branding;
            } else if (e.data?.type === 'wsms-branding-preview-navigate') {
                const route = e.data.route || '/login';
                window.history.pushState(null, '', authUrl(route) + '?preview');
                window.dispatchEvent(new PopStateEvent('popstate'));
            }
        }

        window.addEventListener('message', handleMessage);

        // Signal to parent that preview is ready
        if (window.parent !== window) {
            window.parent.postMessage({ type: 'wsms-branding-preview-ready' }, '*');
        }

        return () => window.removeEventListener('message', handleMessage);
    }, []);

    return children;
}
