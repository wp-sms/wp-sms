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

describe('Email OTP verification', () => {
  const email = uniqueEmail('verify-eotp');
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

  it('should succeed with correct OTP', async () => {
    // Initiate challenge.
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    expect(challengeData.status).toBe('challenge_sent');

    // Get and verify OTP.
    const otp = await getOtp(api, userId, 'email');
    const verifyRes = await api.api('POST', '/auth/verify', {
      challenge_token: challengeData.challenge_token,
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
  });

  it('should reject wrong OTP code', async () => {
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    const verifyRes = await api.api('POST', '/auth/verify', {
      challenge_token: challengeData.challenge_token,
      code: '000000',
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(false);
  });
});

describe('Phone OTP verification', () => {
  const email = uniqueEmail('verify-potp');
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

  it('should succeed with correct phone OTP', async () => {
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'phone',
      identifier: phone,
    });
    const challengeData = await challengeRes.json();

    expect(challengeData.status).toBe('challenge_sent');

    const otp = await getOtp(api, userId, 'phone');
    const verifyRes = await api.api('POST', '/auth/verify', {
      challenge_token: challengeData.challenge_token,
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
  });
});

describe('Magic link verification', () => {
  const email = uniqueEmail('verify-ml');
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

  it('should succeed with correct magic link token', async () => {
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    expect(challengeData.status).toBe('challenge_sent');

    const token = await getMagicLinkToken(api, userId);
    const verifyRes = await api.api('POST', '/auth/verify-magic-link', {
      token,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
  });

  it('should reject invalid magic link token', async () => {
    const verifyRes = await api.api('POST', '/auth/verify-magic-link', {
      token: 'invalid-token-123',
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(false);
  });
});

describe('Resend verification', () => {
  const email = uniqueEmail('verify-resend');
  const password = 'TestPass123!';

  beforeAll(async () => {
    await setSettings(api, scenarios.emailOtpOnly());
    await createUser(api, { email, password });
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should resend challenge', async () => {
    const challengeRes = await api.api('POST', '/auth/login/passwordless', {
      method: 'email',
      identifier: email,
    });
    const challengeData = await challengeRes.json();

    expect(challengeData.challenge_token).toBeDefined();

    const resendRes = await api.api('POST', '/auth/resend', {
      challenge_token: challengeData.challenge_token,
    });
    const resendData = await resendRes.json();

    expect(resendData.success).toBe(true);
  });
});
