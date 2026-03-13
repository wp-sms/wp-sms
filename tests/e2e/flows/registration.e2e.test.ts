import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
import { getOtp } from '../helpers/otp-interceptor';
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

describe('Registration without verification', () => {
  beforeAll(async () => {
    await setSettings(api, scenarios.passwordOnly());
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should register a new user', async () => {
    const email = uniqueEmail('reg-basic');
    const res = await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
    });
    const data = await res.json();

    expect(res.status).toBe(201);
    expect(data.success).toBe(true);
    expect(data.user_id).toBeDefined();
  });

  it('should reject duplicate email', async () => {
    const email = uniqueEmail('reg-dup');
    await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
    });

    api.resetSession();

    const res = await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
    });
    const data = await res.json();

    expect(data.success).toBe(false);
  });
});

describe('Registration with email verification at signup', () => {
  beforeAll(async () => {
    await setSettings(api, scenarios.verifyEmailAtSignup());
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should require email verification after registration', async () => {
    const email = uniqueEmail('reg-everify');
    const res = await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
    });
    const data = await res.json();

    expect(res.status).toBe(201);
    expect(data.success).toBe(true);
    expect(data.pending_verifications).toBeDefined();
    expect(data.pending_verifications).toEqual(
      expect.arrayContaining([expect.objectContaining({ type: 'email' })]),
    );
    expect(data.registration_token).toBeDefined();

    // Intercept OTP.
    const otp = await getOtp(api, data.user_id, 'email_verify');

    // Verify email with registration token.
    const tokenHeaders = { 'X-Registration-Token': data.registration_token };
    const verifyRes = await api.api('POST', '/auth/register/verify/email', {
      code: otp,
    }, tokenHeaders);
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
  });
});

describe('Registration with phone verification at signup', () => {
  beforeAll(async () => {
    await setSettings(api, scenarios.verifyPhoneAtSignup());
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should require phone verification after registration', async () => {
    const email = uniqueEmail('reg-pverify');
    const phone = uniquePhone();
    const res = await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
      phone,
    });
    const data = await res.json();

    expect(res.status).toBe(201);
    expect(data.success).toBe(true);
    expect(data.pending_verifications).toBeDefined();
    expect(data.pending_verifications).toEqual(
      expect.arrayContaining([expect.objectContaining({ type: 'phone' })]),
    );
    expect(data.registration_token).toBeDefined();

    // Intercept OTP.
    const otp = await getOtp(api, data.user_id, 'phone_verify');

    // Verify phone with registration token.
    const tokenHeaders = { 'X-Registration-Token': data.registration_token };
    const verifyRes = await api.api('POST', '/auth/register/verify/phone', {
      code: otp,
    }, tokenHeaders);
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
  });
});

describe('Registration with both email and phone verification', () => {
  beforeAll(async () => {
    await setSettings(api, scenarios.verifyBothAtSignup());
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should require both verifications after registration', async () => {
    const email = uniqueEmail('reg-both');
    const phone = uniquePhone();
    const res = await api.api('POST', '/auth/register', {
      email,
      password: 'TestPass123!',
      phone,
    });
    const data = await res.json();

    expect(res.status).toBe(201);
    expect(data.success).toBe(true);
    expect(data.pending_verifications).toBeDefined();
    expect(data.pending_verifications).toEqual(
      expect.arrayContaining([expect.objectContaining({ type: 'email' })]),
    );
    expect(data.pending_verifications).toEqual(
      expect.arrayContaining([expect.objectContaining({ type: 'phone' })]),
    );

    expect(data.registration_token).toBeDefined();
    const tokenHeaders = { 'X-Registration-Token': data.registration_token };

    // Verify email first.
    const emailOtp = await getOtp(api, data.user_id, 'email_verify');
    const emailVerifyRes = await api.api('POST', '/auth/register/verify/email', {
      code: emailOtp,
    }, tokenHeaders);
    expect((await emailVerifyRes.json()).success).toBe(true);

    // Verify phone.
    const phoneOtp = await getOtp(api, data.user_id, 'phone_verify');
    const phoneVerifyRes = await api.api('POST', '/auth/register/verify/phone', {
      code: phoneOtp,
    }, tokenHeaders);
    expect((await phoneVerifyRes.json()).success).toBe(true);
  });
});

describe('Registration validation', () => {
  beforeAll(async () => {
    await setSettings(api, scenarios.passwordOnly());
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  it('should reject missing email', async () => {
    const res = await api.api('POST', '/auth/register', {
      password: 'TestPass123!',
    });

    expect(res.status).toBeGreaterThanOrEqual(400);
  });
});
