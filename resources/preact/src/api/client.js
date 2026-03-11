const { restUrl, nonce } = window.wsmsAuth || {};

async function request(method, endpoint, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
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
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    put: (url, body) => request('PUT', url, body),
    del: (url, body) => request('DELETE', url, body),
};
