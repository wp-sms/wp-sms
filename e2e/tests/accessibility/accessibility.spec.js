/**
 * Accessibility Tests for WP-SMS Dashboard
 *
 * Uses axe-core to check accessibility issues across all pages.
 * Run with: npm run e2e -- --grep "@accessibility"
 */

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// All dashboard pages to test
const DASHBOARD_PAGES = [
  { id: 'send-sms', name: 'Send SMS' },
  { id: 'outbox', name: 'Outbox' },
  { id: 'subscribers', name: 'Subscribers' },
  { id: 'groups', name: 'Groups' },
  { id: 'overview', name: 'Overview' },
  { id: 'gateway', name: 'Gateway' },
  { id: 'phone', name: 'Phone Config' },
  { id: 'message-button', name: 'Message Button' },
  { id: 'notifications', name: 'Notifications' },
  { id: 'newsletter', name: 'Newsletter' },
  { id: 'integrations', name: 'Integrations' },
  { id: 'advanced', name: 'Advanced' },
];

test.describe('Dashboard Accessibility @accessibility', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the unified admin dashboard
    await page.goto('/wp-admin/admin.php?page=wp-sms-unified-admin');
    // Wait for React app to mount
    await expect(page.locator('#wpsms-settings-root')).toBeVisible({ timeout: 30000 });
    // Wait for initial loading to complete
    await page.waitForTimeout(1000);
  });

  for (const pageConfig of DASHBOARD_PAGES) {
    test(`${pageConfig.name} page should have no accessibility violations`, async ({ page }) => {
      // Navigate to the specific page via sidebar or tab param
      if (pageConfig.id !== 'send-sms') {
        await page.goto(`/wp-admin/admin.php?page=wp-sms-unified-admin&tab=${pageConfig.id}`);
        await expect(page.locator('#wpsms-settings-root')).toBeVisible({ timeout: 30000 });
        // Wait for page content to load
        await page.waitForTimeout(1500);
      }

      // Run axe accessibility scan
      const accessibilityScanResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .exclude('.wpsms-chatbox') // Exclude the chatbox widget
        .exclude('#wpadminbar') // Exclude WordPress admin bar (not our plugin)
        .exclude('#adminmenuwrap') // Exclude WordPress admin menu (not our plugin)
        .analyze();

      // Log violations for debugging
      if (accessibilityScanResults.violations.length > 0) {
        console.log(`\n=== ${pageConfig.name} Page Accessibility Issues ===`);
        accessibilityScanResults.violations.forEach((violation, index) => {
          console.log(`\n${index + 1}. ${violation.id}: ${violation.description}`);
          console.log(`   Impact: ${violation.impact}`);
          console.log(`   Help: ${violation.helpUrl}`);
          console.log(`   Elements affected: ${violation.nodes.length}`);
          violation.nodes.forEach((node, nodeIndex) => {
            console.log(`   ${nodeIndex + 1}. ${node.html.substring(0, 100)}...`);
          });
        });
      }

      // Assert no violations (or specific number of acceptable violations)
      expect(
        accessibilityScanResults.violations,
        `Found ${accessibilityScanResults.violations.length} accessibility violations on ${pageConfig.name} page`
      ).toEqual([]);
    });
  }
});

test.describe('Dashboard Accessibility Summary @accessibility', () => {
  test('Generate accessibility report for all pages', async ({ page }) => {
    const report = {
      timestamp: new Date().toISOString(),
      pages: [],
    };

    for (const pageConfig of DASHBOARD_PAGES) {
      // Navigate to page
      const url = pageConfig.id === 'send-sms'
        ? '/wp-admin/admin.php?page=wp-sms-unified-admin'
        : `/wp-admin/admin.php?page=wp-sms-unified-admin&tab=${pageConfig.id}`;

      await page.goto(url);
      await expect(page.locator('#wpsms-settings-root')).toBeVisible({ timeout: 30000 });
      await page.waitForTimeout(1500);

      // Run accessibility scan
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .exclude('.wpsms-chatbox') // Exclude the chatbox widget
        .exclude('#wpadminbar') // Exclude WordPress admin bar (not our plugin)
        .exclude('#adminmenuwrap') // Exclude WordPress admin menu (not our plugin)
        .analyze();

      // Collect results
      const pageReport = {
        name: pageConfig.name,
        id: pageConfig.id,
        url: url,
        violations: results.violations.map(v => ({
          id: v.id,
          impact: v.impact,
          description: v.description,
          helpUrl: v.helpUrl,
          nodeCount: v.nodes.length,
        })),
        passes: results.passes.length,
        incomplete: results.incomplete.length,
      };

      report.pages.push(pageReport);
    }

    // Print summary
    console.log('\n' + '='.repeat(60));
    console.log('ACCESSIBILITY AUDIT SUMMARY');
    console.log('='.repeat(60));

    let totalViolations = 0;
    let totalPasses = 0;

    report.pages.forEach(pageReport => {
      const violationCount = pageReport.violations.length;
      totalViolations += violationCount;
      totalPasses += pageReport.passes;

      const status = violationCount === 0 ? '✓' : '✗';
      console.log(`\n${status} ${pageReport.name}`);

      if (violationCount > 0) {
        console.log(`  Violations: ${violationCount}`);
        pageReport.violations.forEach(v => {
          console.log(`    - [${v.impact}] ${v.id}: ${v.description}`);
        });
      } else {
        console.log(`  Passes: ${pageReport.passes}, No violations`);
      }
    });

    console.log('\n' + '-'.repeat(60));
    console.log(`Total Pages: ${report.pages.length}`);
    console.log(`Total Violations: ${totalViolations}`);
    console.log(`Total Passes: ${totalPasses}`);
    console.log('='.repeat(60) + '\n');

    // Save report to file
    const fs = await import('fs');
    const reportPath = './e2e-results/accessibility-report.json';
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`Full report saved to: ${reportPath}`);

    // Test passes if we get here - the summary is informational
    expect(true).toBe(true);
  });
});
