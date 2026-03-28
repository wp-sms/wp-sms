import { useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { ErrorBoundary } from 'preact-iso';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { ForgotPassword } from './pages/ForgotPassword';
import { AuthLayout } from './layouts/AuthLayout';
import { BrandingProvider } from './components/BrandingProvider';
import { authConfig, formSlug as formSlugSignal, loadConfig, renderMode } from './signals/config';

const VIEW_COMPONENTS = {
    login: Login,
    register: Register,
    'forgot-password': ForgotPassword,
};

const LOGGED_IN_VIEWS = new Set(['login', 'forgot-password']);

export function EmbedApp({ view = 'register', formSlug = null }) {
    useEffect(() => {
        renderMode.value = 'embed';
        if (window.wsmsAuth?.isLoggedIn && LOGGED_IN_VIEWS.has(view)) return;
        formSlugSignal.value = formSlug;
        authConfig.value = null;
        loadConfig(formSlug, { force: true });
    }, [formSlug, view]);

    if (window.wsmsAuth?.isLoggedIn && LOGGED_IN_VIEWS.has(view)) {
        return (
            <BrandingProvider>
                <AuthLayout title={__('Already Signed In', 'wp-sms')}>
                    <p className="wsms-auth-center wsms-auth-text-sm wsms-auth-text-muted">
                        {__('You are already logged in.', 'wp-sms')}
                    </p>
                </AuthLayout>
            </BrandingProvider>
        );
    }

    const ViewComponent = VIEW_COMPONENTS[view] || Register;

    return (
        <BrandingProvider>
            <ErrorBoundary>
                <ViewComponent />
            </ErrorBoundary>
        </BrandingProvider>
    );
}
