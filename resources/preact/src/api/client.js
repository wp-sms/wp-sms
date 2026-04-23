import { api as sharedApi, setFreshAuthHandler } from '../../../shared/rest-client';

export { setFreshAuthHandler };

/**
 * Preact auth pages call the api with headers as the last positional arg
 * (e.g. `api.get(url, headers)`). The shared client takes an options object
 * instead, so this file adapts the positional signature to `{ headers }`.
 */
export const api = {
    get: (url, headers) => sharedApi.get(url, { headers }),
    post: (url, body, headers) => sharedApi.post(url, body, { headers }),
    put: (url, body, headers) => sharedApi.put(url, body, { headers }),
    del: (url, body, headers) => sharedApi.del(url, body, { headers }),
    upload: (url, formData) => sharedApi.upload(url, formData),
};
