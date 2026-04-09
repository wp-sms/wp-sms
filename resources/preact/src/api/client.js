let { restUrl, nonce } = window.wsmsAuth || {};

const SESSION_EXPIRED = SESSION_EXPIRED;

/**
 * Step-up re-auth gate handler. Consumers (e.g. Profile.jsx) register a
 * handler that opens a modal and returns true on successful step-up; we
 * replay the original request once.
 */
let freshAuthHandler = null;
let pendingStepUp = null;

export function setFreshAuthHandler(handler) {
    freshAuthHandler = handler;
}

function runOneStepUp(info) {
    if (!freshAuthHandler) return Promise.resolve(false);
    // A thrown handler maps to "step up declined" so the singleton always
    // resolves and never gets stuck mid-flight.
    if (pendingStepUp === null) {
        pendingStepUp = Promise.resolve()
            .then(() => freshAuthHandler(info))
            .catch(() => false)
            .finally(() => {
                pendingStepUp = null;
            });
    }
    return pendingStepUp;
}

async function doFetch(method, endpoint, body, extraHeaders) {
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

    return { res, data };
}

async function request(method, endpoint, body = null, extraHeaders = {}) {
    const { res, data } = await doFetch(method, endpoint, body, extraHeaders);

    if (!res.ok) {
        if (res.status === 403 && data?.code === 'rest_cookie_invalid_nonce') {
            throw { status: 403, code: 'nonce_expired', message: SESSION_EXPIRED };
        }

        if (res.status === 403 && (data?.code === 'fresh_auth_required' || data?.error === 'fresh_auth_required')) {
            const info = {
                step_up_methods: data?.data?.step_up_methods ?? [],
                current_freshness_age: data?.data?.current_freshness_age ?? null,
            };
            const ok = await runOneStepUp(info);
            if (ok) {
                const replay = await doFetch(method, endpoint, body, extraHeaders);
                if (!replay.res.ok) {
                    throw { status: replay.res.status, ...replay.data };
                }
                return replay.data;
            }
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
