const { restUrl, nonce } = window.wsmsAuth || {};

async function request(method, endpoint, body = null, extraHeaders = {}) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
            ...extraHeaders,
        },
        credentials: 'same-origin',
    };
    if (body) opts.body = JSON.stringify(body);

    const res = await fetch(`${restUrl}${endpoint.replace(/^\//, '')}`, opts);
    const data = await res.json();

    if (!res.ok) {
        throw { status: res.status, ...data };
    }
    return data;
}

export const api = {
    get: (url, headers) => request('GET', url, null, headers),
    post: (url, body, headers) => request('POST', url, body, headers),
    put: (url, body, headers) => request('PUT', url, body, headers),
    del: (url, body, headers) => request('DELETE', url, body, headers),
};
