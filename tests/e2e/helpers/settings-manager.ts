import type { ApiClient } from './api-client';
import type { AuthSettings } from './types';
import { sleep } from './otp-interceptor';

/**
 * Push auth settings to WordPress via the mu-plugin.
 * Includes a read-back verification with retry to handle WordPress option
 * caching that can cause stale reads when settings change between test files.
 */
export async function setSettings(
  client: ApiClient,
  settings: AuthSettings,
): Promise<void> {
  for (let attempt = 0; attempt < 3; attempt++) {
    const res = await client.e2e('PUT', '/settings', settings);
    const data = await res.json();

    if (!data.ok) {
      throw new Error(`Failed to set settings: ${data.error ?? res.statusText}`);
    }

    // Verify settings were persisted by reading back in a separate request.
    const verify = await getSettings(client);
    const sent = JSON.stringify(settings);
    const stored = JSON.stringify(verify);

    if (sent === stored) {
      return;
    }

    // Settings didn't persist — retry after a short delay.
    await sleep(100);
  }

  throw new Error('Settings verification failed after 3 attempts');
}

/** Get current auth settings from WordPress. */
export async function getSettings(
  client: ApiClient,
): Promise<AuthSettings> {
  const res = await client.e2e('GET', '/settings');
  const data = await res.json();

  if (!data.ok) {
    throw new Error(`Failed to get settings: ${data.error ?? res.statusText}`);
  }

  return data.settings;
}
