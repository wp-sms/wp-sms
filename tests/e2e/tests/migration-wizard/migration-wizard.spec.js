/**
 * Migration Wizard happy-path E2E test.
 *
 * Walks the user through Intro → Review → Preview → Apply → Done with a mocked
 * backend so the test is hermetic and fast. The point is to confirm the wizard
 * surfaces the right copy at each step and that the step indicator advances.
 *
 * @tags @migration-wizard
 */

import { test, expect } from '../../fixtures/test.js';

const FAKE_SCAN = {
  success: true,
  data: {
    country_code: '+98',
    sources: {
      subscribers: { label: 'Subscribers', total: 50, need_fix: 42, already_intl: 8 },
    },
    total_need_fix: 42,
    total_already_intl: 8,
    total_records: 50,
    samples: ['09123456789', '09123456788', '09123456787'],
    backup_exists: false,
    backup_timestamp: null,
    backup_timestamp_iso: null,
    previous_run_sources: [],
    new_sources_since_last: [],
    cc_changed_since_last_run: false,
    last_run_had_errors: false,
  },
};

const FAKE_PREVIEW = {
  success: true,
  data: {
    preview: [
      {
        source: 'subscribers',
        label: 'Subscribers',
        id: 1,
        name: 'Alice',
        original: '09123456789',
        migrated: '+989123456789',
        changed: true,
      },
      {
        source: 'subscribers',
        label: 'Subscribers',
        id: 2,
        name: 'Bob',
        original: '09123456788',
        migrated: '+989123456788',
        changed: true,
      },
    ],
    page: 1,
    per_page: 20,
    total: 42,
    country_code: '+98',
  },
}

const FAKE_EXECUTE = {
  success: true,
  data: {
    counts: { subscribers: 42 },
    total_migrated: 42,
    sources_touched: 1,
    errors: [],
    backup_created: true,
    timestamp: 'April 8, 2026 10:00 (UTC)',
    backup_timestamp: 'April 8, 2026 10:00 (UTC)',
    backup_timestamp_iso: '2026-04-08T10:00:00+00:00',
  },
}

/**
 * Mock the wp_sms_number_migration AJAX endpoint with stage-specific responses.
 */
async function mockMigrationAjax(page) {
  await page.route('**/admin-ajax.php', async (route, request) => {
    const post = request.postData() || ''
    if (!post.includes('action=wp_sms_number_migration')) {
      return route.continue()
    }
    if (post.includes('sub_action=scan')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(FAKE_SCAN) })
    }
    if (post.includes('sub_action=preview')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(FAKE_PREVIEW) })
    }
    if (post.includes('sub_action=execute')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(FAKE_EXECUTE) })
    }
    return route.continue()
  })
}

test.describe('Number Migration Wizard @migration-wizard', () => {
  test('walks through Intro → Review → Preview → Apply → Done', async ({ page, navigateTo }) => {
    await mockMigrationAjax(page)
    await navigateTo('dashboard')

    // The wizard is reachable from the dashboard notice's "Review and fix" action.
    // Open it via the global `wpsms:navigate` event the AppShell listens to.
    await page.evaluate(() => {
      window.dispatchEvent(new CustomEvent('wpsms:open-migration-wizard'))
    })

    // Intro step
    await expect(page.getByText("Let's get your numbers ready for reliable delivery.")).toBeVisible()
    await expect(page.getByText('We back everything up')).toBeVisible()
    await expect(page.getByText('You review before we change anything')).toBeVisible()
    await expect(page.getByText('One-click undo, anytime')).toBeVisible()

    // Start the scan — auto-advances to Review
    await page.getByRole('button', { name: 'Start check' }).click()

    // Review step — summary strip + by-source breakdown + samples disclosure
    await expect(page.getByText('We checked 50 records')).toBeVisible()
    await expect(page.getByText('Total reviewed')).toBeVisible()
    await expect(page.getByText('Need update')).toBeVisible()
    await expect(page.getByText('Already correct')).toBeVisible()

    // Advance to Preview
    await page.getByRole('button', { name: 'Preview changes' }).click()

    // Preview — grouped table + confirmation checkbox
    await expect(page.getByText('Review the changes')).toBeVisible()
    await expect(page.getByText('Subscribers — 2 changes')).toBeVisible()
    await expect(page.getByText("I've reviewed the changes above and I'm ready to apply them.")).toBeVisible()

    // Apply should be disabled until the checkbox is ticked
    const applyButton = page.getByRole('button', { name: 'Apply changes' })
    await expect(applyButton).toBeDisabled()

    // Tick the confirmation
    await page.getByRole('checkbox').click()
    await expect(applyButton).toBeEnabled()
    await applyButton.click()

    // Done step — single primary CTA, success message, undo as low-weight link
    await expect(page.getByText('Done. Your numbers now use the standard format.')).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText(/We updated 42 numbers across 1 sources/)).toBeVisible()
    await expect(page.getByRole('button', { name: 'Close' })).toBeVisible()
    await expect(page.getByText('Undo this update')).toBeVisible()
  })
})
