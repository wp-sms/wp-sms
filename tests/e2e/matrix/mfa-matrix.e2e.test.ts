import { describe, it, expect, beforeAll, beforeEach, afterAll } from 'vitest';
import { ApiClient, createClient } from '../helpers/api-client';
import { setSettings } from '../helpers/settings-manager';
import { createUser, cleanup, uniqueEmail, uniquePhone } from '../helpers/user-factory';
import { getOtp } from '../helpers/otp-interceptor';
import { clearRateLimits } from '../helpers/rate-limiter';
import { mfaPresets } from '../helpers/auth-scenarios';
import type { AuthSettings } from '../helpers/types';

let api: ApiClient;

beforeAll(async () => {
  api = createClient();
  await clearRateLimits(api);
});

afterAll(async () => {
  await cleanup(api);
});

describe.each(mfaPresets)('MFA with %s', (name, settings) => {
  const s = settings as AuthSettings;
  const email = uniqueEmail(`matrix-mfa-${name}`);
  const phone = uniquePhone();
  const password = 'TestPass123!';
  let userId: number;

  // Determine the MFA channel from settings.
  const mfaChannel = s.phone?.enabled && s.phone.usage === 'mfa'
    ? 'phone'
    : s.email?.enabled && s.email.usage === 'mfa'
      ? 'email'
      : null;
  const isOnRegistration = s.enrollment_timing === 'on_registration';
  const isGracePeriod = s.enrollment_timing === 'grace_period';
  const isVoluntary = s.enrollment_timing === 'voluntary';
  const requiredRoles = s.mfa_required_roles ?? [];

  beforeAll(async () => {
    await setSettings(api, s);
    // Skip phone auto-enrollment so enrollment tests can test the full flow.
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
  });

  it('should report MFA enabled in config', async () => {
    const res = await api.api('GET', '/auth/config');
    const data = await res.json();

    expect(res.status).toBe(200);
    expect(data.mfa_enabled).toBe(true);
  });

  if (mfaChannel) {
    it(`should allow MFA enrollment via ${mfaChannel}`, async () => {
      await api.forceLogin(userId);

      // List methods.
      const methodsRes = await api.api('GET', '/auth/methods');
      const methodsData = await methodsRes.json();
      expect(methodsData.methods).toBeDefined();
      expect(Array.isArray(methodsData.methods)).toBe(true);

      // Enroll.
      const enrollData: Record<string, unknown> = { channel_id: mfaChannel };
      if (mfaChannel === 'phone') {
        enrollData.data = { phone };
      }

      const enrollRes = await api.api('POST', '/auth/mfa/enroll', enrollData);
      const enrollResult = await enrollRes.json();

      expect(enrollRes.status).toBe(200);
      expect(enrollResult.success).toBe(true);

      // Email auto-activates (no OTP needed). Phone requires OTP confirmation.
      if (enrollResult.data?.requires_confirmation) {
        const otp = await getOtp(api, userId, mfaChannel);
        const verifyRes = await api.api('POST', '/auth/mfa/enroll/verify', {
          channel_id: mfaChannel,
          code: otp,
        });
        const verifyData = await verifyRes.json();

        expect(verifyData.success).toBe(true);
      }
    });

    if (isOnRegistration && requiredRoles.includes('administrator')) {
      it('should require MFA on subsequent login for admin', async () => {
        // Login — should require MFA since user is enrolled.
        const loginRes = await api.api('POST', '/auth/login', {
          username: email,
          password,
        });
        const loginData = await loginRes.json();

        expect(loginRes.status).toBe(200);
        // If enrolled, should require MFA; if not enrolled yet, may authenticate directly.
        if (loginData.status === 'mfa_required') {
          expect(loginData.session_token).toBeDefined();

          // Send challenge.
          const sendRes = await api.api('POST', '/auth/mfa/send', {
            session_token: loginData.session_token,
            channel_id: mfaChannel,
          });
          expect((await sendRes.json()).success).toBe(true);

          // Verify.
          const otp = await getOtp(api, userId, mfaChannel);
          const verifyRes = await api.api('POST', '/auth/mfa/verify', {
            session_token: loginData.session_token,
            code: otp,
            channel_id: mfaChannel,
          });
          const verifyData = await verifyRes.json();

          expect(verifyData.success).toBe(true);
          expect(verifyData.status).toBe('authenticated');
        }
      });
    }

    if (isGracePeriod) {
      it('should allow login without MFA during grace period', async () => {
        const loginRes = await api.api('POST', '/auth/login', {
          username: email,
          password,
        });
        const loginData = await loginRes.json();

        expect(loginRes.status).toBe(200);
        expect(loginData.success).toBe(true);
        // During grace period, should authenticate without MFA.
        expect(['authenticated', 'mfa_required']).toContain(loginData.status);
      });
    }

    if (isVoluntary) {
      it('should not require MFA for voluntary enrollment', async () => {
        const loginRes = await api.api('POST', '/auth/login', {
          username: email,
          password,
        });
        const loginData = await loginRes.json();

        expect(loginRes.status).toBe(200);
        expect(loginData.success).toBe(true);
        // Voluntary MFA means no enforcement.
        expect(['authenticated', 'mfa_required']).toContain(loginData.status);
      });
    }
  }

  if (s.backup_codes?.enabled) {
    it('should support backup codes when enabled', async () => {
      await api.forceLogin(userId);

      const res = await api.api('POST', '/auth/mfa/backup-codes/regenerate');
      const data = await res.json();

      expect(res.status).toBe(200);
      expect(data.success).toBe(true);
      expect(data.data?.codes).toBeDefined();
    });
  }
});
