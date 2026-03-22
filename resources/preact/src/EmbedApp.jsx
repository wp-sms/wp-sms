import { useEffect } from 'preact/hooks';
import { ErrorBoundary } from 'preact-iso';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { ForgotPassword } from './pages/ForgotPassword';
import { BrandingProvider } from './components/BrandingProvider';
import { authConfig, formSlug as formSlugSignal, loadConfig, renderMode } from './signals/config';

const VIEW_COMPONENTS = {
    login: Login,
    register: Register,
    'forgot-password': ForgotPassword,
};

export function EmbedApp({ view = 'register', formSlug = null }) {
    useEffect(() => {
        renderMode.value = 'embed';
        formSlugSignal.value = formSlug;
        authConfig.value = null;
        loadConfig(formSlug, { force: true });
    }, [formSlug]);

    const ViewComponent = VIEW_COMPONENTS[view] || Register;

    return (
        <BrandingProvider>
            <ErrorBoundary>
                <ViewComponent />
            </ErrorBoundary>
        </BrandingProvider>
    );
}
