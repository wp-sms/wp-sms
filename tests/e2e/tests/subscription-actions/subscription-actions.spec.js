import { test, expect } from '../../fixtures/test.js';

const openSubscriptionForm = async (page, mobile) => {
  await page.goto('/?wpsms_e2e_structured_actions=1');
  await expect(page.locator('script[src*="/public/js/frontend.min.js"]')).toHaveCount(1);

  await page.evaluate(() => {
    window.__wpsmsAttack = false;
  });

  const form = page.locator('.js-wpSmsSubscribeForm');
  await expect(form).toBeVisible();
  await form.locator('.js-wpSmsSubscriberName input').fill('Structured action test');
  await form.locator('.js-wpSmsSubscriberMobile input').fill(mobile);

  const gdprCheckbox = form.locator('.js-wpSmsGdprConfirmation');
  if (await gdprCheckbox.count()) {
    await gdprCheckbox.check();
  }

  return form;
};

test('renders a safe structured SMS action through the public AJAX flow', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));

  const form = await openSubscriptionForm(page, '+155****4567');
  const [ajaxResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('admin-ajax.php?action=wp_sms_subscribe')),
    form.locator('.js-wpSmsSubmitButton').click(),
  ]);

  expect(ajaxResponse.status()).toBe(400);
  const response = await ajaxResponse.json();
  expect(response.success).toBe(false);
  expect(response.data.message).toContain('&lt;script&gt;');
  expect(response.data.actions).toEqual([
    {
      label: 'Text START',
      href: 'sms:+155****4567?body=START',
      type: 'sms',
      target: '_blank',
      rel: 'nofollow noopener noreferrer',
    },
  ]);

  const message = form.locator('.wpsms-subscribe__message--error');
  const link = message.getByRole('link', { name: 'Text START' });

  await expect(message).toBeVisible();
  await expect(message).toContainText('&lt;script&gt;');
  await expect(message.locator('script, img')).toHaveCount(0);
  await expect(link).toHaveAttribute('href', 'sms:+155****4567?body=START');
  await expect(link).toHaveAttribute('target', '_blank');
  await expect(link).toHaveAttribute('rel', 'nofollow noopener noreferrer');
  await expect(link).not.toHaveAttribute('onclick');
  await expect(link).not.toHaveAttribute('onerror');
  await expect(link).not.toHaveAttribute('style');
  await expect(form.getByRole('link', { name: 'Unsafe action' })).toHaveCount(0);
  await expect.poll(() => page.evaluate(() => window.__wpsmsAttack)).toBe(false);
  expect(pageErrors).toEqual([]);
});

test('keeps plain validation errors escaped and backward-compatible', async ({ page }) => {
  const form = await openSubscriptionForm(page, '+155****4568');
  const [ajaxResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().includes('admin-ajax.php?action=wp_sms_subscribe')),
    form.locator('.js-wpSmsSubmitButton').click(),
  ]);

  expect(ajaxResponse.status()).toBe(400);
  const response = await ajaxResponse.json();
  expect(typeof response.data).toBe('string');
  expect(response.data).toContain('&lt;img');

  const message = form.locator('.wpsms-subscribe__message--error');
  await expect(message).toBeVisible();
  await expect(message).toContainText('&lt;img');
  await expect(message.locator('img, script, a')).toHaveCount(0);
});
