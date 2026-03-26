import { challengeToken, challengeMeta, pendingMfa, pendingVerifications, authStep, clearAuth, isRedirecting } from '../signals/auth';

export function redirectTo(url) {
    isRedirecting.value = true;
    window.location.href = url;
}
import { formRedirectUrl } from '../signals/config';
import { getBaseUrl } from './urls';

export function handleAuthResponse(res, _route) {
    if (res.status === 'authenticated') {
        if (res.meta?.grace_period) {
            sessionStorage.setItem('wsms_grace_period', JSON.stringify(res.meta.grace_period));
        }
        redirectTo(formRedirectUrl.value || res.redirect || getBaseUrl());
        return;
    }

    if (res.status === 'mfa_enrollment_required') {
        redirectTo(getBaseUrl() + '/security?mfa_enroll=required');
        return 'mfa_enrollment_required';
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

/**
 * Extract structured error details from an API error response.
 *
 * @returns {{ message: string, code: string|null, recoveryAction: string|null, retryAfter: number|null }}
 */
export function extractError(err) {
    return {
        message: err.message || err.error || 'Something went wrong. Please try again.',
        code: err.error || null,
        recoveryAction: err.recovery_action || null,
        retryAfter: err.meta?.retry_after || null,
    };
}

/**
 * Handle recovery actions from structured error details.
 * Returns true if the action was handled (caller should stop), false otherwise.
 *
 * @param {{ recoveryAction: string|null, retryAfter: number|null }} details
 * @param {Function} route - preact-iso route function
 * @param {{ setCooldown?: Function }} opts - optional handlers for specific actions
 */
export function handleRecoveryAction(details, route, opts = {}) {
    if (details.recoveryAction === 'restart_flow') {
        clearAuth();
        route(getBaseUrl() + '/login');
        return true;
    }
    if (details.recoveryAction === 'wait_retry' && details.retryAfter && opts.setCooldown) {
        opts.setCooldown(details.retryAfter);
    }
    return false;
}

export function formatWebAuthnError(err) {
    if (err.name === 'NotAllowedError') return 'Verification was cancelled or timed out. Try again.';
    if (err.name === 'InvalidStateError') return 'This device is already registered.';
    return extractError(err).message;
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
    redirectTo(getBaseUrl() + '/login');
}
