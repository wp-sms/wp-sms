import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
import { getOtp, getMagicLinkToken } from '../helpers/otp-interceptor';
import { clearRateLimits } from '../helpers/rate-limiter';
import { primaryPresets } from '../helpers/auth-scenarios';
import type { AuthSettings } from '../helpers/types';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
  await clearRateLimits(api);
});

afterAll(async () => {
  await cleanup(api);
});

describe.each(primaryPresets)('Login with %s', (name, settings) => {
  const email = uniqueEmail(`matrix-login-${name}`);
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  const s = settings as AuthSettings;
  const hasPassword = s.password?.enabled === true;
  const hasEmail = s.email?.enabled === true;
  const hasPhone = s.phone?.enabled === true;
  const emailMethods = s.email?.verification_methods ?? [];
  const phoneMethods = s.phone?.verification_methods ?? [];
  const emailUsesOtp = emailMethods.includes('otp');
  const emailUsesMagicLink = emailMethods.includes('magic_link');
  const phoneUsesOtp = phoneMethods.includes('otp');

  beforeAll(async () => {
    await setSettings(api, s);
    const user = await createUser(api, { email, password, phone });
    userId = user.user_id;
  });

  beforeEach(async () => {
    api.resetSession();
    await clearRateLimits(api);
  });

  if (hasPassword) {
    it('should login with password', async () => {
      const res = await api.api('POST', '/auth/login', {
        username: email,
        password,
      });
      const data = await res.json();

      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      // Could be 'authenticated' or 'mfa_required' depending on MFA config.
      expect(['authenticated', 'mfa_required']).toContain(data.status);
    });
  }

  if (hasEmail && emailUsesOtp) {
    it('should login via email OTP', async () => {
      const challengeRes = await api.api('POST', '/auth/login/passwordless', {
        method: 'email',
        identifier: email,
      });
      const challengeData = await challengeRes.json();

      expect(challengeData.success).toBe(true);
      expect(challengeData.status).toBe('challenge_sent');

      const otp = await getOtp(api, userId, 'email');
      const verifyRes = await api.api('POST', '/auth/verify', {
        challenge_token: challengeData.challenge_token,
        code: otp,
      });
      const verifyData = await verifyRes.json();

      expect(verifyData.success).toBe(true);
      expect(verifyData.status).toBe('authenticated');
    });
  }

  if (hasEmail && emailUsesMagicLink) {
    it('should login via email magic link', async () => {
      const challengeRes = await api.api('POST', '/auth/login/passwordless', {
        method: 'email',
        identifier: email,
      });
      const challengeData = await challengeRes.json();

      expect(challengeData.success).toBe(true);
      expect(challengeData.status).toBe('challenge_sent');

      const token = await getMagicLinkToken(api, userId);
      const verifyRes = await api.api('POST', '/auth/verify-magic-link', { token });
      const verifyData = await verifyRes.json();

      expect(verifyData.success).toBe(true);
      expect(verifyData.status).toBe('authenticated');
    });
  }

  if (hasPhone && phoneUsesOtp) {
    it('should login via phone OTP', async () => {
      const challengeRes = await api.api('POST', '/auth/login/passwordless', {
        method: 'phone',
        identifier: phone,
      });
      const challengeData = await challengeRes.json();

      expect(challengeData.success).toBe(true);
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
  }

  it('should return correct config', async () => {
    const res = await api.api('GET', '/auth/config');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.primary_methods).toBeDefined();
    expect(Array.isArray(data.primary_methods)).toBe(true);

    if (hasPassword) {
      expect(data.primary_methods).toContain('password');
    }
    if (hasEmail && s.email?.usage === 'login') {
      expect(data.primary_methods).toContain('email');
    }
    if (hasPhone && s.phone?.usage === 'login') {
      expect(data.primary_methods).toContain('phone');
    }
  });
});
