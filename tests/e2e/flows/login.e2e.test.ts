import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
import { getOtp, getMagicLinkToken } from '../helpers/otp-interceptor';
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

describe('Password login', () => {
  const email = uniqueEmail('login-pw');
  const password = 'TestPass123!';
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

  it('should login with correct credentials', async () => {
    const res = await api.api('POST', '/auth/login', {
      username: email,
      password,
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
    expect(data.status).toBe('authenticated');
    expect(data.user).toBeDefined();
    expect(data.user.id).toBe(userId);
  });

  it('should reject wrong password', async () => {
    const res = await api.api('POST', '/auth/login', {
      username: email,
      password: 'wrongpass',
    });
    const data = await res.json();

    expect(data.success).toBe(false);
    expect(res.status).toBe(401);
  });

  it('should reject unknown user', async () => {
    const res = await api.api('POST', '/auth/login', {
      username: 'nonexistent@e2e.test',
      password: 'whatever',
    });
    const data = await res.json();

    expect(data.success).toBe(false);
  });
});

describe('Email OTP passwordless login', () => {
  const email = uniqueEmail('login-eotp');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.emailOtpOnly());
    const user = await createUser(api, { email, password });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should complete OTP passwordless login flow', async () => {
    // Step 1: Initiate passwordless login.
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    expect(challengeRes.status).toBe(200);
    expect(challengeData.success).toBe(true);
    expect(challengeData.status).toBe('challenge_sent');
    expect(challengeData.challenge_token).toBeDefined();

    // Step 2: Intercept OTP.
    const otp = await getOtp(api, userId, 'email');

    // Step 3: Verify OTP.
    const verifyRes = await api.api('POST', '/auth/verify', {
      challenge_token: challengeData.challenge_token,
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyRes.status).toBe(200);
    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
    expect(verifyData.user).toBeDefined();
    expect(verifyData.user.id).toBe(userId);
  });
});

describe('Email magic link login', () => {
  const email = uniqueEmail('login-ml');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.emailMagicLinkOnly());
    const user = await createUser(api, { email, password });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should complete magic link login flow', async () => {
    // Step 1: Initiate passwordless login.
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    expect(challengeRes.status).toBe(200);
    expect(challengeData.success).toBe(true);
    expect(challengeData.status).toBe('challenge_sent');

    // Step 2: Intercept magic link token.
    const token = await getMagicLinkToken(api, userId);

    // Step 3: Verify magic link.
    const verifyRes = await api.api('POST', '/auth/verify-magic-link', {
      token,
    });
    const verifyData = await verifyRes.json();

    expect(verifyRes.status).toBe(200);
    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
    expect(verifyData.user.id).toBe(userId);
  });
});

describe('Phone OTP passwordless login', () => {
  const email = uniqueEmail('login-potp');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.phoneOtpOnly());
    const user = await createUser(api, { email, password, phone });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should complete phone OTP passwordless login flow', async () => {
    // Step 1: Initiate passwordless login.
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'phone',
      identifier: phone,
    });
    const challengeData = await challengeRes.json();

    expect(challengeRes.status).toBe(200);
    expect(challengeData.success).toBe(true);
    expect(challengeData.status).toBe('challenge_sent');
    expect(challengeData.challenge_token).toBeDefined();

    // Step 2: Intercept OTP.
    const otp = await getOtp(api, userId, 'phone');

    // Step 3: Verify OTP.
    const verifyRes = await api.api('POST', '/auth/verify', {
      challenge_token: challengeData.challenge_token,
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyRes.status).toBe(200);
    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
    expect(verifyData.user.id).toBe(userId);
  });
});

describe('Identify endpoint', () => {
  const email = uniqueEmail('login-identify');
  const password = 'TestPass123!';

  beforeAll(async () => {
    await setSettings(api, scenarios.passwordAndEmailOtp());
    await createUser(api, { email, password });
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should return available methods for a known user', async () => {
    const res = await api.api('POST', '/auth/identify', {
      identifier: email,
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.user_found).toBe(true);
    expect(data.available_methods).toBeDefined();
    expect(data.available_methods.length).toBeGreaterThan(0);
  });
});
