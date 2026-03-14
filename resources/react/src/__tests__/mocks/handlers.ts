import { http, HttpResponse } from 'msw';
import type { AuthSettings } from '@/lib/api';
import { DEFAULTS } from '@/lib/constants';
import { deepMerge } from '@/lib/utils';

const BASE_URL = 'https://example.com/wp-json/wsms/v1';

let mockSettings: AuthSettings = { ...DEFAULTS };

const mockLogs = [
  {
    id: 1,
    user_id: 1,
    event: 'login_success',
    status: 'success',
    ip_address: '192.168.1.1',
    channel_id: null,
    user_agent: 'Mozilla/5.0 (Macintosh)',
    meta: null,
    created_at: '2025-01-15T10:30:00Z',
    user_display: { display_name: 'Admin', email: 'admin@example.com' },
  },
  {
    id: 2,
    user_id: 2,
    event: 'login_failure',
    status: 'failure',
    ip_address: '10.0.0.1',
    channel_id: null,
    user_agent: null,
    meta: { reason: 'invalid_password' },
    created_at: '2025-01-15T10:25:00Z',
    user_display: { display_name: 'John', email: 'john@example.com' },
  },
  {
    id: 3,
    user_id: 1,
    event: 'otp_sent',
    status: 'success',
    ip_address: '192.168.1.1',
    channel_id: 'phone',
    user_agent: 'Mozilla/5.0 (Macintosh)',
    meta: { channel: 'phone', method: 'sms' },
    created_at: '2025-01-15T10:20:00Z',
    user_display: { display_name: 'Admin', email: 'admin@example.com' },
  },
];

export function resetMockSettings() {
  mockSettings = { ...DEFAULTS };
}

export const handlers = [
  http.get(`${BASE_URL}/auth/admin/settings`, () => {
    return HttpResponse.json({
      success: true,
      settings: mockSettings,
    });
  }),

  http.put(`${BASE_URL}/auth/admin/settings`, async ({ request }) => {
    const body = (await request.json()) as Partial<AuthSettings>;
    mockSettings = deepMerge(mockSettings as Required<AuthSettings>, body) as AuthSettings;
    return HttpResponse.json({
      success: true,
      message: 'Settings updated.',
      settings: mockSettings,
    });
  }),

  http.get(`${BASE_URL}/auth/admin/logs`, ({ request }) => {
    const url = new URL(request.url);
    const event = url.searchParams.get('event');
    const status = url.searchParams.get('status');
    const page = Number(url.searchParams.get('page') || '1');
    const perPage = Number(url.searchParams.get('per_page') || '20');

    let filtered = [...mockLogs];
    if (event) filtered = filtered.filter((l) => l.event === event);
    if (status) filtered = filtered.filter((l) => l.status === status);

    const start = (page - 1) * perPage;
    const items = filtered.slice(start, start + perPage);

    return HttpResponse.json({
      success: true,
      items,
      total: filtered.length,
      page,
      per_page: perPage,
    });
  }),

  http.delete(`${BASE_URL}/auth/admin/logs`, () => {
    return HttpResponse.json({
      success: true,
      deleted: mockLogs.length,
      message: `Deleted ${mockLogs.length} log entries.`,
    });
  }),

  http.delete(`${BASE_URL}/auth/admin/users/:id/mfa`, () => {
    return HttpResponse.json({
      success: true,
      message: 'All MFA factors have been disabled for this user.',
    });
  }),
];
