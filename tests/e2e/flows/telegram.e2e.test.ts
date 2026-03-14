import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { createClient } from '../helpers/api-client';
import { setSettings, getSettings } from '../helpers/settings-manager';
import { cleanup } from '../helpers/user-factory';
import * as scenarios from '../helpers/auth-scenarios';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
});

afterAll(async () => {
  await cleanup(api);
});

describe('Telegram social login config', () => {
  beforeEach(async () => {
    api.resetSession();
  });

  it('should include telegram in social_providers when enabled', async () => {
    await setSettings(api, scenarios.telegramSocialLogin());

    const res = await api.api('GET', '/auth/config');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.social_providers).toBeDefined();
    expect(data.social_providers).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          id: 'telegram',
          name: 'Telegram',
          authorize_url: expect.stringContaining('/auth/social/authorize/telegram'),
        }),
      ]),
    );
  });

  it('should not include telegram when social is disabled', async () => {
    await setSettings(api, scenarios.passwordOnly());

    const res = await api.api('GET', '/auth/config');
    const data = await res.json();

    expect(res.status).toBe(200);
    // social_providers should either be absent or not contain telegram.
    const providers = data.social_providers ?? [];
    const telegramProvider = providers.find((p: { id: string }) => p.id === 'telegram');
    expect(telegramProvider).toBeUndefined();
  });

  it('should show both google and telegram when both enabled', async () => {
    await setSettings(api, scenarios.googleAndTelegramSocial());

    const res = await api.api('GET', '/auth/config');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.social_providers).toBeDefined();
    expect(data.social_providers).toHaveLength(2);

    const ids = data.social_providers.map((p: { id: string }) => p.id);
    expect(ids).toContain('google');
    expect(ids).toContain('telegram');
  });
});

describe('Telegram settings persistence', () => {
  beforeEach(async () => {
    api.resetSession();
  });

  it('should persist telegram social settings', async () => {
    const settings = scenarios.telegramSocialLogin();
    await setSettings(api, settings);

    const stored = await getSettings(api);

    expect(stored.social?.telegram?.enabled).toBe(true);
    expect(stored.social?.telegram?.client_id).toBe('test-telegram-client-id');
    expect(stored.social?.telegram?.client_secret).toBe('test-telegram-client-secret');
  });

  it('should persist telegram MFA settings', async () => {
    const settings = scenarios.telegramSocialWithMfa();
    await setSettings(api, settings);

    const stored = await getSettings(api);

    expect(stored.telegram?.enabled).toBe(true);
    expect(stored.telegram?.bot_username).toBe('test_bot');
    expect(stored.telegram?.code_length).toBe(6);
    expect(stored.telegram?.expiry).toBe(300);
    expect(stored.telegram?.max_attempts).toBe(3);
    expect(stored.telegram?.cooldown).toBe(60);
  });
});

// Note: Authorize endpoint tests (redirect to oauth.telegram.org) are not
// included because they require network access to fetch the OIDC discovery
// document from Telegram's servers. The OIDC flow is covered by unit tests.
