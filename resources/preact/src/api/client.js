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

async function uploadRequest(method, endpoint, formData) {
    const res = await fetch(`${restUrl}${endpoint.replace(/^\//, '')}`, {
        method,
        headers: { 'X-WP-Nonce': nonce },
        credentials: 'same-origin',
        body: formData,
    });
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
    upload: (url, formData) => uploadRequest('POST', url, formData),
};
