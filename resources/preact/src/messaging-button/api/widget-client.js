async function request(method, endpoint, body, extraHeaders = {}) {
    const { restUrl, nonce } = window.wsmsMessagingButtonConfig || {};

    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...extraHeaders,
        },
        credentials: 'same-origin',
    };

    if (nonce) {
        opts.headers['X-WP-Nonce'] = nonce;
    }

    if (body) {
        opts.body = JSON.stringify(body);
    }

    const res = await fetch(`${restUrl}${endpoint}`, opts);
    const data = await res.json();

    if (!res.ok) {
        throw data;
    }

    return data;
}

export function submitMessage(data, extraHeaders = {}) {
    return request('POST', 'messaging-button/message', data, extraHeaders);
}
