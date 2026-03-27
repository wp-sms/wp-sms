import { useEffect } from 'preact/hooks';
import { authStep, authError, challengeToken, pendingVerifications, resetIdentifyFlow } from '../signals/auth';
import { primaryMethods } from '../signals/config';
import { authUrl, getQueryParam } from '../utils/urls';
import { friendlySocialError, handleAuthResponse } from '../utils/auth';
import { api } from '../api/client';
import { AuthLayout } from '../layouts/AuthLayout';
import { AuthLink } from '../components/AuthLink';
import { alreadySignedIn } from '../components/AlreadySignedIn';
import { IdentifierStep } from '../components/steps/IdentifierStep';
import { AuthenticateStep } from '../components/steps/AuthenticateStep';
import { MfaStep } from '../components/steps/MfaStep';
import { ProgressiveRegisterStep } from '../components/steps/ProgressiveRegisterStep';
import { RegisterVerifyStep } from '../components/steps/RegisterVerifyStep';
import { LoginVerifyStep } from '../components/steps/LoginVerifyStep';

const TITLES = {
    identifier: 'Sign In',
    authenticate: 'Sign In',
    mfa: 'Verify Your Identity',
    register: 'Create Account',
    register_verify: 'Verify Your Account',
    login_verify: 'Verify Your Account',
};

export function Login() {
    const step = authStep.value;
    const hasPassword = primaryMethods.value.includes('password');

    // Handle redirect-based auth params (social login, wp-login.php, WooCommerce).
    useEffect(() => {
        const socialError = getQueryParam('social_error');
        const mfaToken = getQueryParam('social_mfa') || getQueryParam('wp_mfa');
        const verifyToken = getQueryParam('wp_verify');

        if (socialError) {
            authError.value = friendlySocialError(socialError);
            window.history.replaceState({}, '', window.location.pathname);
        } else if (mfaToken) {
            window.history.replaceState({}, '', window.location.pathname);
            resolveMfaSession(mfaToken);
        } else if (verifyToken) {
            window.history.replaceState({}, '', window.location.pathname);
            resolveVerifySession(verifyToken);
        }
    }, []);

    const guard = alreadySignedIn();
    if (guard) return guard;

    const footer = step === 'register' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            Already have an account? Sign in
        </AuthLink>
    ) : step === 'register_verify' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            Skip for now
        </AuthLink>
    ) : step === 'login_verify' ? null : step === 'identifier' ? (
        <div className="wsms-auth-flex-gap">
            {hasPassword && <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink>}
            <AuthLink href={authUrl('/register')}>Create account</AuthLink>
        </div>
    ) : (
        hasPassword ? <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink> : null
    );

    return (
        <AuthLayout title={TITLES[step] || 'Sign In'} footer={footer}>
            <div className="wsms-auth-fade-in">
                {step === 'identifier' && <IdentifierStep />}
                {step === 'authenticate' && <AuthenticateStep />}
                {step === 'mfa' && <MfaStep />}
                {step === 'register' && <ProgressiveRegisterStep />}
                {step === 'register_verify' && <RegisterVerifyStep />}
                {step === 'login_verify' && <LoginVerifyStep />}
            </div>
        </AuthLayout>
    );
}

async function resolveMfaSession(token) {
    try {
        const res = await api.post('/auth/mfa/factors', { session_token: token });
        handleAuthResponse(res);
    } catch {
        authError.value = 'Your session has expired. Please sign in again.';
    }
}

async function resolveVerifySession(token) {
    try {
        challengeToken.value = token;
        const res = await api.get('/auth/register/status', { 'X-Auth-Session': token });

        if (res.pending_verifications?.length > 0) {
            pendingVerifications.value = res.pending_verifications;
            // Determine step based on whether this is a registration or login verification.
            const isRegistration = res.pending_verifications.some((v) => v.context === 'registration');
            authStep.value = isRegistration ? 'register_verify' : 'login_verify';
        } else {
            // Already verified — try to complete.
            const completeRes = await api.post('/auth/verification/complete', null, { 'X-Auth-Session': token });
            handleAuthResponse(completeRes);
        }
    } catch {
        authError.value = 'Your session has expired. Please sign in again.';
    }
}
