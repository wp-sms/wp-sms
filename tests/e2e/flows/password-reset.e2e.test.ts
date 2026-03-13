import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail } from '../helpers/user-factory';
import { clearRateLimits } from '../helpers/rate-limiter';
import * as scenarios from '../helpers/auth-scenarios';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
  await clearRateLimits(api);
});

afterAll(async () => {
  await cleanup(api);
});

describe('Password reset flow', () => {
  const email = uniqueEmail('pw-reset');
  const password = 'OldPass123!';
  const newPassword = 'NewPass456!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.passwordOnly());
    const user = await createUser(api, { email, password });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should initiate password reset', async () => {
    const res = await api.api('POST', '/auth/forgot-password', {
      email,
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
    expect(data.message).toContain('reset');
  });

  it('should accept forgot-password for non-existent email without revealing', async () => {
    const res = await api.api('POST', '/auth/forgot-password', {
      email: 'nonexistent@e2e.test',
    });
    const data = await res.json();

    // Should succeed silently to prevent user enumeration.
    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
  });

  it('should reject reset with invalid token', async () => {
    const res = await api.api('POST', '/auth/reset-password', {
      token: 'invalid-token-xyz',
      password: newPassword,
    });
    const data = await res.json();

    expect(data.success).toBe(false);
  });
});
