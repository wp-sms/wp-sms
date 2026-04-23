import { __ } from '@wordpress/i18n';
import { api as sharedApi, setFreshAuthHandler } from '../../../shared/rest-client';

const SESSION_EXPIRED = () => __('Your session has expired. Please sign in again.', 'wp-sms');

/**
 * Map shared-client errors to the shape the Preact UI expects.
 *
 * - `rest_cookie_invalid_nonce` → `{ code: 'nonce_expired', message: SESSION_EXPIRED }`
 *   so the auth UI can prompt re-auth instead of showing the raw WP message.
 * - Parse failures (non-JSON body) → `{ code: 'parse_error' }` with a generic
 *   message, mirroring the previous try/catch behavior.
 *
 * Step-up re-auth (`fresh_auth_required`) is handled transparently in the
 * shared client via `setFreshAuthHandler`.
 */
function adaptError(error) {
    if (error?.code === 'rest_cookie_invalid_nonce') {
        return { status: 403, code: 'nonce_expired', message: SESSION_EXPIRED() };
    }
    if (error && typeof error === 'object') {
        return error;
    }
    return {
        status: 0,
        code: 'parse_error',
        message: __('The server returned an unexpected response. Please try again.', 'wp-sms'),
    };
}

async function wrap(promise) {
    try {
        return await promise;
    } catch (error) {
        throw adaptError(error);
    }
}

export { setFreshAuthHandler };

export const api = {
    get: (url, headers) => wrap(sharedApi.get(url, { headers })),
    post: (url, body, headers) => wrap(sharedApi.post(url, body, { headers })),
    put: (url, body, headers) => wrap(sharedApi.put(url, body, { headers })),
    del: (url, body, headers) => wrap(sharedApi.del(url, body, { headers })),
    upload: (url, formData) => wrap(sharedApi.upload(url, formData)),
};
