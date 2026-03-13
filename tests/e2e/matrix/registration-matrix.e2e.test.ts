import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
import { getOtp } from '../helpers/otp-interceptor';
import { clearRateLimits } from '../helpers/rate-limiter';
import { verificationPresets } from '../helpers/auth-scenarios';
import type { AuthSettings } from '../helpers/types';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
  await clearRateLimits(api);
});

afterAll(async () => {
  await cleanup(api);
});

describe.each(verificationPresets)(
  'Registration with %s',
  (name, settings) => {
    const s = settings as AuthSettings;
    const requiresEmailVerify = s.email?.verify_at_signup === true;
    const requiresPhoneVerify = s.phone?.verify_at_signup === true;

    beforeAll(async () => {
      await setSettings(api, s);
    });

    beforeEach(async () => {
      api.resetSession();
      await clearRateLimits(api);
    });

    it('should register and handle expected verifications', async () => {
      const email = uniqueEmail(`matrix-reg-${name}`);
      const phone = uniquePhone();

      const registerPayload: Record<string, string> = {
        email,
        password: 'TestPass123!',
      };
      if (requiresPhoneVerify) {
        registerPayload.phone = phone;
      }

      const res = await api.api('POST', '/auth/register', registerPayload);
      const data = await res.json();

      expect(res.status).toBe(201);
      expect(data.success).toBe(true);

      if (requiresEmailVerify || requiresPhoneVerify) {
        expect(data.pending_verifications).toBeDefined();
        expect(data.registration_token).toBeDefined();
      }

      const tokenHeaders = data.registration_token
        ? { 'X-Registration-Token': data.registration_token }
        : undefined;

      if (requiresEmailVerify) {
        expect(data.pending_verifications).toEqual(
          expect.arrayContaining([expect.objectContaining({ type: 'email' })]),
        );

        // Complete email verification.
        const emailOtp = await getOtp(api, data.user_id, 'email_verify');
        const emailVerifyRes = await api.api(
          'POST',
          '/auth/register/verify/email',
          { code: emailOtp },
          tokenHeaders,
        );
        expect((await emailVerifyRes.json()).success).toBe(true);
      }

      if (requiresPhoneVerify) {
        expect(data.pending_verifications).toEqual(
          expect.arrayContaining([expect.objectContaining({ type: 'phone' })]),
        );

        // Complete phone verification.
        const phoneOtp = await getOtp(api, data.user_id, 'phone_verify');
        const phoneVerifyRes = await api.api(
          'POST',
          '/auth/register/verify/phone',
          { code: phoneOtp },
          tokenHeaders,
        );
        expect((await phoneVerifyRes.json()).success).toBe(true);
      }
    });

    it('should reflect verification config in /auth/config', async () => {
      const configRes = await api.api('GET', '/auth/config');
      const config = await configRes.json();

      expect(config.require_email_verification).toBe(requiresEmailVerify);
      expect(config.require_phone_verification).toBe(requiresPhoneVerify);
    });
  },
);
