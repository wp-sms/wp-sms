import type { ApiClient } from './api-client';

/** Clear all rate limit transients in WordPress. */
export async function clearRateLimits(client: ApiClient): Promise<void> {
  const res = await client.e2e('POST', '/clear-rate-limits');
  const data = await res.json();

  if (!data.ok) {
    throw new Error(`Failed to clear rate limits: ${data.error ?? res.statusText}`);
  }
}
