import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail } from '../helpers/user-factory';
import { clearRateLimits } from '../helpers/rate-limiter';
import * as scenarios from '../helpers/auth-scenarios';
import * as OTPAuth from 'otpauth';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
  await clearRateLimits(api);
});

afterAll(async () => {
  await cleanup(api);
});

describe('TOTP enrollment', () => {
  const email = uniqueEmail('totp-enroll');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaTotpForAdmin());
    const user = await createUser(api, {
      email,
      password,
      role: 'administrator',
    });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
    await api.forceLogin(userId);
  });

  it('should enroll in TOTP and receive QR code', async () => {
    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'totp',
    });
    const enrollData = await enrollRes.json();

    expect(enrollRes.status).toBe(200);
    expect(enrollData.success).toBe(true);
    expect(enrollData.data.requires_confirmation).toBe(true);
    expect(enrollData.data.qr_code_uri).toMatch(/^data:image\/svg\+xml;base64,/);
    expect(enrollData.data.secret).toBeDefined();
    expect(enrollData.data.secret.length).toBeGreaterThan(0);
  });

  it('should confirm enrollment with valid TOTP code', async () => {
    // Start enrollment.
    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'totp',
    });
    const enrollData = await enrollRes.json();
    expect(enrollData.success).toBe(true);

    const secret = enrollData.data.secret;

    // Generate a valid TOTP code from the shared secret.
    const totp = new OTPAuth.TOTP({ secret });
    const code = totp.generate();

    // Confirm enrollment.
    const verifyRes = await api.api('POST', '/auth/mfa/enroll/verify', {
      channel_id: 'totp',
      code,
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(true);
    expect(verifyData.message).toContain('enrolled');
  });

  it('should reject confirmation with invalid code', async () => {
    // Start enrollment.
    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'totp',
    });
    const enrollData = await enrollRes.json();
    expect(enrollData.success).toBe(true);

    // Try invalid code.
    const verifyRes = await api.api('POST', '/auth/mfa/enroll/verify', {
      channel_id: 'totp',
      code: '000000',
    });
    const verifyData = await verifyRes.json();

    expect(verifyData.success).toBe(false);
  });
});

describe('TOTP login flow', () => {
  const email = uniqueEmail('totp-login');
  const password = 'TestPass123!';
  let userId: number;
  let totpSecret: string;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaTotpForAdmin());
    const user = await createUser(api, {
      email,
      password,
      role: 'administrator',
    });
    userId = user.user_id;

    // Enroll TOTP for this user.
    api.resetSession();
    await api.forceLogin(userId);

    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'totp',
    });
    const enrollData = await enrollRes.json();
    totpSecret = enrollData.data.secret;

    const totp = new OTPAuth.TOTP({ secret: totpSecret });
    const code = totp.generate();

    await api.api('POST', '/auth/mfa/enroll/verify', {
      channel_id: 'totp',
      code,
    });
  });

  beforeEach(() => {
    api.resetSession();
  });

  it('should require TOTP after password login', async () => {
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

    // Step 2: Send MFA challenge (no-op for TOTP).
    const sendRes = await api.api('POST', '/auth/mfa/send', {
      challenge_token: loginData.challenge_token,
      channel_id: 'totp',
    });
    const sendData = await sendRes.json();
    expect(sendData.success).toBe(true);

    // Step 3: Verify with generated TOTP code.
    const totp = new OTPAuth.TOTP({ secret: totpSecret });
    const code = totp.generate();

    const verifyRes = await api.api('POST', '/auth/mfa/verify', {
      challenge_token: loginData.challenge_token,
      code,
      channel_id: 'totp',
    });
    const verifyData = await verifyRes.json();

    expect(verifyRes.status).toBe(200);
    expect(verifyData.success).toBe(true);
    expect(verifyData.status).toBe('authenticated');
    expect(verifyData.user).toBeDefined();
    expect(verifyData.user.id).toBe(userId);
  });
});

describe('TOTP unenroll', () => {
  const email = uniqueEmail('totp-unenroll');
  const password = 'TestPass123!';
  let userId: number;

  beforeAll(async () => {
    await setSettings(api, scenarios.mfaTotpForAdmin());
    const user = await createUser(api, {
      email,
      password,
      role: 'administrator',
    });
    userId = user.user_id;

    // Enroll TOTP.
    api.resetSession();
    await api.forceLogin(userId);

    const enrollRes = await api.api('POST', '/auth/mfa/enroll', {
      channel_id: 'totp',
    });
    const enrollData = await enrollRes.json();

    const totp = new OTPAuth.TOTP({ secret: enrollData.data.secret });
    await api.api('POST', '/auth/mfa/enroll/verify', {
      channel_id: 'totp',
      code: totp.generate(),
    });
  });

  it('should unenroll from TOTP', async () => {
    api.resetSession();
    await api.forceLogin(userId);

    const res = await api.api('DELETE', '/auth/mfa/unenroll', {
      channel_id: 'totp',
    });
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
  });
});
