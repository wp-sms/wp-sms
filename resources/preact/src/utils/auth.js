import { __, sprintf } from '@wordpress/i18n';
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
        message: err.message || err.error || __('Something went wrong. Please try again.', 'wp-sms'),
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
    if (err.name === 'NotAllowedError') return __('Verification was cancelled or timed out. Try again.', 'wp-sms');
    if (err.name === 'InvalidStateError') return __('This device is already registered.', 'wp-sms');
    return extractError(err).message;
}

function getSocialErrorMessages() {
    return {
        registration_disabled: __('No account found. Create an account first.', 'wp-sms'),
        missing_params: __('Social login failed. Please try again.', 'wp-sms'),
        missing_email: __('Registration failed. Please try again or use a different method.', 'wp-sms'),
    };
}

export function friendlySocialError(code) {
    const messages = getSocialErrorMessages();
    return messages[code] ?? sprintf(__('Social login failed: %s', 'wp-sms'), code);
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
