/**
 * Global Teardown for Playwright E2E Tests
 *
 * This file runs after all tests complete.
 * Cleans up any test data or resources.
 */

export default async function globalTeardown() {
  console.log('\n=== E2E Test Global Teardown ===\n');

  // Cleanup logic can be added here:
  // - Clear test data from database
  // - Reset plugin settings
  // - Clean up uploaded files

  console.log('Teardown complete.');
}
