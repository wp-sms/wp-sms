import { authStep, authError, resetIdentifyFlow } from '../signals/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { AuthLink } from '../components/AuthLink';
import { IdentifierStep } from '../components/steps/IdentifierStep';
import { AuthenticateStep } from '../components/steps/AuthenticateStep';
import { MfaStep } from '../components/steps/MfaStep';
import { ProgressiveRegisterStep } from '../components/steps/ProgressiveRegisterStep';
import { RegisterVerifyStep } from '../components/steps/RegisterVerifyStep';

const TITLES = {
    identifier: 'Sign In',
    authenticate: 'Sign In',
    mfa: 'Verify Your Identity',
    register: 'Create Account',
    register_verify: 'Verify Your Account',
};

export function Login() {
    const step = authStep.value;

    const footer = step === 'register' ? (
        <AuthLink href={authUrl('/login')} onClick={() => (authStep.value = 'identifier')}>
            Already have an account? Sign in
        </AuthLink>
    ) : step === 'register_verify' ? (
        <AuthLink href={authUrl('/login')} onClick={() => resetIdentifyFlow()}>
            Skip for now
        </AuthLink>
    ) : step === 'identifier' ? (
        <div className="flex gap-4">
            <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink>
            <AuthLink href={authUrl('/register')}>Create account</AuthLink>
        </div>
    ) : (
        <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink>
    );

    return (
        <AuthLayout title={TITLES[step] || 'Sign In'} footer={footer}>
            <div className="animate-fade-in">
                {step === 'identifier' && <IdentifierStep />}
                {step === 'authenticate' && <AuthenticateStep />}
                {step === 'mfa' && <MfaStep />}
                {step === 'register' && <ProgressiveRegisterStep />}
                {step === 'register_verify' && <RegisterVerifyStep />}
            </div>
        </AuthLayout>
    );
}
