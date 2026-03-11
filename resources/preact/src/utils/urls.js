export function getBaseUrl() {
    return window.wsmsAuth?.baseUrl || '/account';
}

export function authUrl(path) {
    return `${getBaseUrl()}${path}`;
}

export function getQueryParam(name) {
    return new URLSearchParams(window.location.search).get(name) || '';
}
