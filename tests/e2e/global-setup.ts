import { createClient } from './helpers/api-client';
import { cleanup } from './helpers/user-factory';

export async function setup() {
  const client = createClient();

  // Health check — verify mu-plugin is active.
  const healthRes = await client.e2e('GET', '/health');

  if (!healthRes.ok) {
    throw new Error(
      `E2E health check failed (${healthRes.status}). ` +
      'Ensure the wsms-e2e-test-helper.php mu-plugin is installed and ' +
      'WSMS_E2E_SECRET env var is set in both WordPress and the test runner.',
    );
  }

  const health = await healthRes.json();
  if (!health.ok) {
    throw new Error('E2E health check returned ok=false');
  }

  console.log('[e2e] Health check passed — mu-plugin is active');

  // Initial cleanup.
  const result = await cleanup(client);
  console.log(`[e2e] Initial cleanup: ${result.users_deleted} users deleted`);
}

export async function teardown() {
  const client = createClient();

  // Final cleanup.
  try {
    await cleanup(client);
    console.log('[e2e] Final cleanup complete');
  } catch {
    console.warn('[e2e] Final cleanup failed (non-fatal)');
  }
}
