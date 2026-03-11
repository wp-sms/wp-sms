import { challengeToken, challengeMeta, pendingMfa, clearAuth } from '../signals/auth';
import { authUrl, getBaseUrl } from './urls';

export function handleAuthResponse(res, route) {
    if (res.status === 'authenticated') {
        clearAuth();
        window.location.href = res.redirect || getBaseUrl();
        return;
    }

    if (res.status === 'mfa_required') {
        pendingMfa.value = {
            available_factors: res.meta?.available_factors,
            challenge_token: res.challenge_token,
        };
        route(authUrl('/verify'));
        return;
    }

    if (res.status === 'challenge_sent') {
        challengeToken.value = res.challenge_token;
        challengeMeta.value = res.meta || null;
        return 'challenge_sent';
    }
}

export function extractError(err) {
    return err.message || 'Something went wrong. Please try again.';
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
