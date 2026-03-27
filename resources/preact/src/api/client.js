let { restUrl, nonce } = window.wsmsAuth || {};

const SESSION_EXPIRED = SESSION_EXPIRED;

async function request(method, endpoint, body = null, extraHeaders = {}) {
    const isFormData = body instanceof FormData;
    const headers = { 'X-WP-Nonce': nonce, ...extraHeaders };
    if (!isFormData) headers['Content-Type'] = 'application/json';

    const opts = {
        method,
        headers,
        credentials: 'same-origin',
    };
    if (body) opts.body = isFormData ? body : JSON.stringify(body);

    const res = await fetch(`${restUrl}${endpoint.replace(/^\//, '')}`, opts);

    let data;
    try {
        data = await res.json();
    } catch {
        throw {
            status: res.status,
            code: 'parse_error',
            message: res.status === 403
                ? SESSION_EXPIRED
                : 'The server returned an unexpected response. Please try again.',
        };
    }

    if (!res.ok) {
        if (res.status === 403 && data?.code === 'rest_cookie_invalid_nonce') {
            throw { status: 403, code: 'nonce_expired', message: SESSION_EXPIRED };
        }
        throw { status: res.status, ...data };
    }
    return data;
}

/** Allow external code to update the nonce (e.g. after a heartbeat refresh). */
export function setNonce(newNonce) {
    nonce = newNonce;
}

export const api = {
    get: (url, headers) => request('GET', url, null, headers),
    post: (url, body, headers) => request('POST', url, body, headers),
    put: (url, body, headers) => request('PUT', url, body, headers),
    del: (url, body, headers) => request('DELETE', url, body, headers),
    upload: (url, formData) => request('POST', url, formData),
};
