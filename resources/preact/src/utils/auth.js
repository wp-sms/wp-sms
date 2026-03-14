import { challengeToken, challengeMeta, pendingMfa, pendingVerifications, authStep, clearAuth } from '../signals/auth';
import { authUrl, getBaseUrl } from './urls';

export function handleAuthResponse(res, route) {
    if (res.status === 'authenticated') {
        clearAuth();
        window.location.href = res.redirect || getBaseUrl();
        return;
    }

    if (res.status === 'verification_required') {
        pendingVerifications.value = res.meta?.pending_verifications || [];
        challengeToken.value = res.session_token;
        authStep.value = 'login_verify';
        return 'verification_required';
    }

    if (res.status === 'mfa_required') {
        pendingMfa.value = {
            available_factors: res.meta?.available_factors,
            session_token: res.session_token,
        };
        authStep.value = 'mfa';
        return 'mfa_required';
    }

    if (res.status === 'challenge_sent') {
        challengeToken.value = res.session_token;
        challengeMeta.value = res.meta || null;
        return 'challenge_sent';
    }
}

export function extractError(err) {
    return err.message || 'Something went wrong. Please try again.';
}

const SOCIAL_ERROR_MESSAGES = {
    registration_disabled: 'No account found. Create an account first.',
    missing_params: 'Social login failed. Please try again.',
    missing_email: 'Registration failed. Please try again or use a different method.',
};

export function friendlySocialError(code) {
    return SOCIAL_ERROR_MESSAGES[code] ?? `Social login failed: ${code}`;
}

export async function logout() {
    const { api } = await import('../api/client');
    try {
        await api.post('/auth/logout');
    } catch {
        // proceed with redirect regardless
    }
    window.location.href = getBaseUrl() + '/login';
}
