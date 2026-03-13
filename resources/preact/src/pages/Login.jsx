import { authStep, authError, resetIdentifyFlow } from '../signals/auth';
import { primaryMethods } from '../signals/config';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { AuthLink } from '../components/AuthLink';
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

    const footer = step === 'register' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            Already have an account? Sign in
        </AuthLink>
    ) : step === 'register_verify' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            Skip for now
        </AuthLink>
    ) : step === 'login_verify' ? null : step === 'identifier' ? (
        <div className="flex gap-4">
            {hasPassword && <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink>}
            <AuthLink href={authUrl('/register')}>Create account</AuthLink>
        </div>
    ) : (
        hasPassword ? <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink> : null
    );

    return (
        <AuthLayout title={TITLES[step] || 'Sign In'} footer={footer}>
            <div className="animate-fade-in">
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
