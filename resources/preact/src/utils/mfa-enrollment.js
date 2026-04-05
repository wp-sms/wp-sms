import { __ } from '@wordpress/i18n';
import { api } from '../api/client';
import { extractError, formatWebAuthnError } from './auth';

/**
 * Start enrollment for an MFA channel.
 *
 * @param {string} channelId
 * @param {object} data - Channel-specific data (e.g. { phone } for phone channel)
 * @returns {Promise<{ success: boolean, message: string, data: object }|null>}
 */
export async function enrollChannel(channelId, data = {}) {
    const res = await api.post('/auth/mfa/enroll', { channel_id: channelId, data });
    return res;
}

/**
 * Verify enrollment with a confirmation code or attestation.
 *
 * @param {string} channelId
 * @param {string} code
 * @returns {Promise<{ success: boolean, message: string, data: object }>}
 */
export async function verifyEnrollment(channelId, code) {
    return api.post('/auth/mfa/enroll/verify', { channel_id: channelId, code });
}

/**
 * Start WebAuthn passkey registration flow.
 *
 * @param {object} creationOptions - PublicKeyCredentialCreationOptions from server
 * @returns {Promise<string>} JSON-stringified attestation response
 */
export async function startPasskeyRegistration(creationOptions) {
    const { startRegistration } = await import('@simplewebauthn/browser');
    const attestation = await startRegistration({ optionsJSON: creationOptions });
    return JSON.stringify(attestation);
}

/**
 * Check if the browser supports passkeys (WebAuthn).
 *
 * @returns {{ supported: boolean, reason?: string }}
 */
export function checkPasskeySupport() {
    if (!window.isSecureContext) {
        return { supported: false, reason: 'no_https' };
    }
    if (!window.PublicKeyCredential) {
        return { supported: false, reason: 'no_webauthn' };
    }
    return { supported: true };
}

/**
 * Dismiss the voluntary MFA prompt ("Don't show again").
 */
export async function dismissMfaPrompt() {
    return api.post('/auth/mfa/dismiss-prompt');
}

/**
 * Load available MFA methods from the server.
 *
 * @returns {Promise<Array>} Methods filtered to those supporting MFA
 */
export async function loadMfaMethods() {
    const res = await api.get('/auth/methods');
    return (res.methods || []).filter((m) => m.supports_mfa);
}

export { extractError, formatWebAuthnError };
