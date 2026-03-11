import { LocationProvider, Router, Route, ErrorBoundary } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { loadConfig } from './signals/config';
import { authUrl, getBaseUrl } from './utils/urls';
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

export function App() {
    useEffect(() => {
        loadConfig();
    }, []);

    return (
        <LocationProvider scope={getBaseUrl()}>
            <div class="wsms-card">
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
            </div>
        </LocationProvider>
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
