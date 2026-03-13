import type { ApiClient } from './api-client';
import type { OtpResponse, MagicLinkResponse } from './types';

const POLL_INTERVAL = 300;
const MAX_RETRIES = 20; // 6 seconds total

/**
 * Get the plaintext OTP captured by the mu-plugin.
 * Retries with polling since OTP generation is async.
 */
export async function getOtp(
  client: ApiClient,
  userId: number,
  type = 'email_verify',
): Promise<string> {
  for (let i = 0; i < MAX_RETRIES; i++) {
    if (i > 0) await sleep(POLL_INTERVAL);

    const res = await client.e2e('GET', `/otp?user_id=${userId}&type=${type}`);
    const data: OtpResponse = await res.json();

    if (data.ok && data.otp) {
      return data.otp;
    }
  }

  throw new Error(
    `OTP not found after ${MAX_RETRIES} retries (user_id=${userId}, type=${type})`,
  );
}

/**
 * Get the plaintext magic link token captured by the mu-plugin.
 * Retries with polling since token generation is async.
 */
export async function getMagicLinkToken(
  client: ApiClient,
  userId: number,
): Promise<string> {
  for (let i = 0; i < MAX_RETRIES; i++) {
    if (i > 0) await sleep(POLL_INTERVAL);

    const res = await client.e2e('GET', `/magic-link-token?user_id=${userId}`);
    const data: MagicLinkResponse = await res.json();

    if (data.ok && data.token) {
      return data.token;
    }
  }

  throw new Error(
    `Magic link token not found after ${MAX_RETRIES} retries (user_id=${userId})`,
  );
}

export function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
