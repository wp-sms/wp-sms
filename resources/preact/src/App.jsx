import { LocationProvider, Router, Route, ErrorBoundary } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { signal } from '@preact/signals';
import { loadConfig } from './signals/config';
import { authUrl, getBaseUrl } from './utils/urls';
import { Dialog } from './components/ui/Dialog';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { ForgotPassword } from './pages/ForgotPassword';
import { ResetPassword } from './pages/ResetPassword';
import { VerifyOtp } from './pages/VerifyOtp';
import { VerifyToken } from './pages/VerifyToken';
import { Account } from './pages/Account';
import { Profile } from './pages/Profile';
import { ChangePassword } from './pages/ChangePassword';
import { Security } from './pages/Security';

// Popup state signals
export const popupOpen = signal(false);
export const popupView = signal('login');

const VIEW_COMPONENTS = {
    login: Login,
    register: Register,
    'forgot-password': ForgotPassword,
    'reset-password': ResetPassword,
    verify: VerifyOtp,
    'verify-magic-link': VerifyMagicLinkPage,
    'verify-email': VerifyEmailPage,
    account: Account,
    profile: Profile,
    'change-password': ChangePassword,
    security: Security,
};

export function App({ mode = 'fullpage' }) {
    useEffect(() => {
        loadConfig();
    }, []);

    if (mode === 'popup') {
        return <PopupApp />;
    }

    return <FullPageApp />;
}

function FullPageApp() {
    return (
        <LocationProvider scope={getBaseUrl()}>
            <ErrorBoundary>
                <Router>
                    <Route path={authUrl('/login')} component={Login} />
                    <Route path={authUrl('/register')} component={Register} />
                    <Route path={authUrl('/forgot-password')} component={ForgotPassword} />
                    <Route path={authUrl('/reset-password')} component={ResetPassword} />
                    <Route path={authUrl('/verify')} component={VerifyOtp} />
                    <Route path={authUrl('/verify-magic-link')} component={VerifyMagicLinkPage} />
                    <Route path={authUrl('/verify-email')} component={VerifyEmailPage} />
                    <Route path={authUrl('/profile')} component={Profile} />
                    <Route path={authUrl('/change-password')} component={ChangePassword} />
                    <Route path={authUrl('/security')} component={Security} />
                    <Route path={authUrl('/')} component={AccountOrLogin} />
                    <Route default component={AccountOrLogin} />
                </Router>
            </ErrorBoundary>
        </LocationProvider>
    );
}

function closePopup() {
    popupOpen.value = false;
}

function PopupApp() {
    const view = popupView.value;
    const ViewComponent = VIEW_COMPONENTS[view] || Login;

    return (
        <Dialog open={popupOpen.value} onClose={closePopup}>
            <ErrorBoundary>
                <ViewComponent />
            </ErrorBoundary>
        </Dialog>
    );
}

function AccountOrLogin() {
    return window.wsmsAuth?.isLoggedIn ? <Account /> : <Login />;
}

function VerifyMagicLinkPage() {
    return (
        <VerifyToken
            title="Magic Link"
            endpoint="/auth/verify-magic-link"
            loadingText="Verifying your magic link\u2026"
            errorText="Invalid or expired magic link."
            successRedirect
        />
    );
}

function VerifyEmailPage() {
    return (
        <VerifyToken
            title="Email Verification"
            endpoint="/auth/verify-email"
            loadingText="Verifying your email\u2026"
            errorText="Invalid or expired verification token."
            successText="Email verified successfully!"
            successLinkText="Sign in to your account"
        />
    );
}
