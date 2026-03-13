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

describe('Profile (authenticated)', () => {
  const email = uniqueEmail('profile');
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
    await api.forceLogin(userId);
  });

  it('should get current user profile via /auth/me', async () => {
    const res = await api.api('GET', '/auth/me');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.user).toBeDefined();
    expect(data.user.id).toBe(userId);
    expect(data.user.email).toBe(email);
  });

  it('should update profile fields', async () => {
    const res = await api.api('PUT', '/auth/profile', {
      display_name: 'E2E Test User',
      first_name: 'E2E',
      last_name: 'Test',
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
  });

  it('should change password', async () => {
    const newPassword = 'NewPass789!';
    const res = await api.api('PUT', '/auth/password', {
      current_password: password,
      new_password: newPassword,
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);

    // Verify can login with new password.
    api.resetSession();
    const loginRes = await api.api('POST', '/auth/login', {
      username: email,
      password: newPassword,
    });
    const loginData = await loginRes.json();

    expect(loginData.success).toBe(true);
    expect(loginData.status).toBe('authenticated');
  });
});

describe('Profile email verification', () => {
  const email = uniqueEmail('profile-everify');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.passwordAndEmailOtp());
    const user = await createUser(api, { email, password });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await api.forceLogin(userId);
  });

  it('should send and verify email inline', async () => {
    // Send verification.
    const sendRes = await api.api('POST', '/auth/profile/send-email-verification');
    const sendData = await sendRes.json();

    expect(sendData.success).toBe(true);

    // Intercept OTP.
    const otp = await getOtp(api, userId, 'email_verify');

    // Verify.
    const verifyRes = await api.api('POST', '/auth/profile/verify-email', {
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
  });
});

describe('Profile phone verification', () => {
  const email = uniqueEmail('profile-pverify');
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.passwordAndPhoneOtp());
    const user = await createUser(api, { email, password, phone });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await api.forceLogin(userId);
  });

  it('should send and verify phone inline', async () => {
    // Send verification.
    const sendRes = await api.api('POST', '/auth/profile/send-phone-verification');
    const sendData = await sendRes.json();

    expect(sendData.success).toBe(true);

    // Intercept OTP.
    const otp = await getOtp(api, userId, 'phone_verify');

    // Verify.
    const verifyRes = await api.api('POST', '/auth/profile/verify-phone', {
      code: otp,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
  });
});

describe('Logout', () => {
  const email = uniqueEmail('logout');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.passwordOnly());
    const user = await createUser(api, { email, password });
    userId = user.user_id;
  });

  it('should logout successfully', async () => {
    api.resetSession();
    await api.api('POST', '/auth/login', {
      username: email,
      password,
    });
    await api.fetchNonce(userId);

    const res = await api.api('POST', '/auth/logout');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
  });
});
