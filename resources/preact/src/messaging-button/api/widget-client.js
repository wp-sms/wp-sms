import { api } from '../../../../shared/rest-client';

export function submitMessage(data, extraHeaders = {}) {
    return api.post('messaging-button/message', data, { headers: extraHeaders });
}
