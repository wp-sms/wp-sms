const { restUrl, nonce } = window.wsmsAuth || {};

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
    upload: (url, formData) => request('POST', url, formData),
};
