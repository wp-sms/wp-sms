import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
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

describe('MFA enrollment', () => {
  const email = uniqueEmail('mfa-enroll');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaPhoneForAdmin());
    // Create user with phone meta but skip factor auto-enrollment — we test enrollment below.
    const user = await createUser(api, {
      email,
      password,
      phone,
      role: 'administrator',
      auto_enroll_phone: false,
    });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
    await api.forceLogin(userId);
  });

  it('should list available MFA methods', async () => {
    const res = await api.api('GET', '/auth/methods');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.methods).toBeDefined();
    expect(Array.isArray(data.methods)).toBe(true);
  });

  it('should list enrolled factors', async () => {
    const res = await api.api('GET', '/auth/factors');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.factors).toBeDefined();
    expect(Array.isArray(data.factors)).toBe(true);
  });

  it('should enroll in phone MFA and verify', async () => {
    // Start enrollment.
    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'phone',
      data: { phone },
    });
    const enrollData = await enrollRes.json();

    expect(enrollRes.status).toBe(200);
    expect(enrollData.success).toBe(true);

    // Intercept OTP.
    const otp = await getOtp(api, userId, 'phone');

    // Verify enrollment.
    const verifyRes = await api.api('POST', '/auth/mfa/enroll/verify', {
      channel_id: 'phone',
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
  });
});

describe('MFA login flow', () => {
  const email = uniqueEmail('mfa-login');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaPhoneForAdmin());
    // Create user WITH phone — mu-plugin auto-enrolls the phone factor.
    const user = await createUser(api, {
      email,
      password,
      phone,
      role: 'administrator',
    });
    userId = user.user_id;
  });

  beforeEach(() => {
    api.resetSession();
  });

  it('should require MFA after password login', async () => {
    // Step 1: Login with password.
    const loginRes = await api.api('POST', '/auth/login', {
      username: email,
      password,
    });
    const loginData = await loginRes.json();

    expect(loginRes.status).toBe(200);
    expect(loginData.success).toBe(true);
    expect(loginData.status).toBe('mfa_required');
    expect(loginData.challenge_token).toBeDefined();
    expect(loginData.meta?.available_factors).toBeDefined();

    // Step 2: Send MFA challenge.
    const sendRes = await api.api('POST', '/auth/mfa/send', {
      challenge_token: loginData.challenge_token,
      channel_id: 'phone',
    });
    const sendData = await sendRes.json();

    expect(sendData.success).toBe(true);

    // Step 3: Intercept OTP.
    const otp = await getOtp(api, userId, 'phone');

    // Step 4: Verify MFA.
    const verifyRes = await api.api('POST', '/auth/mfa/verify', {
      challenge_token: loginData.challenge_token,
      code: otp,
      channel_id: 'phone',
    });
    const verifyData = await verifyRes.json();

    expect(verifyRes.status).toBe(200);
    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
    expect(verifyData.user).toBeDefined();
    expect(verifyData.user.id).toBe(userId);
  });
});

describe('MFA unenroll', () => {
  const email = uniqueEmail('mfa-unenroll');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaVoluntary());
    // Create user WITH phone — mu-plugin auto-enrolls the phone factor.
    const user = await createUser(api, {
      email,
      password,
      phone,
      role: 'administrator',
    });
    userId = user.user_id;
  });

  it('should unenroll from MFA factor', async () => {
    api.resetSession();
    await api.forceLogin(userId);

    const res = await api.api('DELETE', '/auth/mfa/unenroll', {
      channel_id: 'phone',
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
  });
});

describe('Backup codes', () => {
  const email = uniqueEmail('mfa-backup');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaWithBackupCodes());
    const user = await createUser(api, {
      email,
      password,
      phone,
      role: 'administrator',
    });
    userId = user.user_id;
  });

  it('should regenerate backup codes', async () => {
    api.resetSession();
    await api.forceLogin(userId);

    const res = await api.api('POST', '/auth/mfa/backup-codes/regenerate');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
    expect(data.data?.codes).toBeDefined();
    expect(Array.isArray(data.data.codes)).toBe(true);
  });
});
