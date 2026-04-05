import { useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { authStep, authError, challengeToken, pendingVerifications, resetIdentifyFlow, stepDirection } from '../signals/auth';
import { primaryMethods, legalLinks, registrationEnabled } from '../signals/config';
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
import { StepTransition } from '../components/ui/StepTransition';

function getTitles() {
    return {
        identifier: __('Sign In', 'wp-sms'),
        authenticate: __('Sign In', 'wp-sms'),
        mfa: __('Verify Your Identity', 'wp-sms'),
        register: __('Create Account', 'wp-sms'),
        register_verify: __('Verify Your Account', 'wp-sms'),
        login_verify: __('Verify Your Account', 'wp-sms'),
    };
}

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

    const legal = legalLinks.value;
    const hasLegal = legal && (legal.terms_url || legal.privacy_url);

    const legalFooter = hasLegal && (
        <p className="wsms-auth-center wsms-auth-text-xs wsms-auth-text-muted wsms-auth-mt-1">
            {legal.terms_url && (
                <a href={legal.terms_url} target="_blank" rel="noopener noreferrer" className="wsms-auth-legal-link">{__('Terms of Service', 'wp-sms')}</a>
            )}
            {legal.terms_url && legal.privacy_url && ` ${__('and', 'wp-sms')} `}
            {legal.privacy_url && (
                <a href={legal.privacy_url} target="_blank" rel="noopener noreferrer" className="wsms-auth-legal-link">{__('Privacy Policy', 'wp-sms')}</a>
            )}
        </p>
    );

    const TITLES = getTitles();
    const footer = step === 'register' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            {__('Already have an account? Sign in', 'wp-sms')}
        </AuthLink>
    ) : step === 'register_verify' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            {__('Skip for now', 'wp-sms')}
        </AuthLink>
    ) : step === 'login_verify' ? null : step === 'identifier' ? (
        <>
            <div className="wsms-auth-flex-gap">
                {hasPassword && <AuthLink href={authUrl('/forgot-password')}>{__('Forgot password?', 'wp-sms')}</AuthLink>}
                {registrationEnabled.value && <AuthLink href={authUrl('/register')}>{__('Create account', 'wp-sms')}</AuthLink>}
            </div>
            {legalFooter}
        </>
    ) : (
        <>
            {hasPassword ? <AuthLink href={authUrl('/forgot-password')}>{__('Forgot password?', 'wp-sms')}</AuthLink> : null}
            {legalFooter}
        </>
    );

    return (
        <AuthLayout title={TITLES[step] || __('Sign In', 'wp-sms')} footer={footer}>
            <StepTransition step={step} direction={stepDirection.value}>
                {step === 'identifier' && <IdentifierStep />}
                {step === 'authenticate' && <AuthenticateStep />}
                {step === 'mfa' && <MfaStep />}
                {step === 'register' && <ProgressiveRegisterStep />}
                {step === 'register_verify' && <RegisterVerifyStep />}
                {step === 'login_verify' && <LoginVerifyStep />}
            </StepTransition>
        </AuthLayout>
    );
}

async function resolveMfaSession(token) {
    try {
        const res = await api.post('/auth/mfa/factors', { session_token: token });
        handleAuthResponse(res);
    } catch {
        authError.value = __('Your session has expired. Please sign in again.', 'wp-sms');
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
        authError.value = __('Your session has expired. Please sign in again.', 'wp-sms');
    }
}
