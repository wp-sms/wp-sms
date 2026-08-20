import { test, expect } from '../../fixtures/test.js';

const smsHref = 'sms:+15555554567?&body=START';
const validationMessage = [
  'You are currently unsubscribed.<br><br>',
  `<a href="${smsHref}" target="_blank" rel="noopener">Text START</a>`,
  '<br>Or text START to +1 (555) 555-4567',
].join('');

const untrustedErrorMessage = [
  '<script>window.__wpsmsAttack = true</script>',
  '<img src="x" onerror="window.__wpsmsAttack = true">',
  '<a href="javascript:window.__wpsmsAttack = true" onclick="window.__wpsmsAttack = true" style="color:red">Unsafe link</a>',
].join('');

test('renders a server-sanitized subscription validation link as HTML', async ({ page }) => {
  await page.route('**/wp-admin/admin-ajax.php?**', async (route) => {
    await route.fulfill({
      status: 400,
      contentType: 'application/json',
      body: JSON.stringify({ success: false, data: validationMessage }),
    });
  });

  await page.goto('/');
  await page.evaluate((gdprErrorText) => {
    document.body.innerHTML = `
      <form class="js-wpSmsSubscribeForm">
        <div class="js-wpSmsSubscriberName"><input type="text"></div>
        <div class="js-wpSmsSubscriberMobile"><input type="tel"></div>
        <input class="js-wpSmsSubscribeType" type="radio" value="subscribe" checked>
        <input class="js-wpSmsGdprConfirmation" type="checkbox">
        <button class="js-wpSmsSubmitTypeButton js-wpSmsSubmitButton">Subscribe</button>
        <div class="js-wpSmsSubscribeOverlay" style="display:none"></div>
        <div class="js-wpSmsSubscribeStepOne"></div>
        <div class="js-wpSmsSubscribeStepTwo" style="display:none"></div>
        <div class="js-wpSmsSubscribeMessage"></div>
      </form>
    `;

    window.wpsms_ajax_object = {
      subscribe_ajax_url: '/wp-admin/admin-ajax.php?action=wp_sms_subscribe&_nonce=test',
      unsubscribe_ajax_url: '/wp-admin/admin-ajax.php?action=wp_sms_unsubscribe&_nonce=test',
      gdpr_error_text: gdprErrorText,
      unknown_error: 'Unknown error',
      loading_text: 'Loading...',
      subscribe_text: 'Subscribe',
    };

    window.__wpsmsAttack = false;
  }, untrustedErrorMessage);

  await page.addScriptTag({ url: '/wp-includes/js/jquery/jquery.min.js' });
  await page.addScriptTag({ url: '/wp-content/plugins/wp-sms/public/js/frontend.min.js' });

  await page.locator('.js-wpSmsSubscriberName input').fill('Browser QA');
  await page.locator('.js-wpSmsSubscriberMobile input').fill('+155****4567');

  const message = page.locator('.wpsms-subscribe__message--error');

  await page.locator('.js-wpSmsSubmitButton').click();

  await expect(message).toContainText('<script>');
  await expect(message.locator('script, img, a')).toHaveCount(0);
  await expect.poll(() => page.evaluate(() => window.__wpsmsAttack)).toBe(false);

  await page.locator('.js-wpSmsGdprConfirmation').check();
  await page.locator('.js-wpSmsSubmitButton').click();

  const link = message.getByRole('link', { name: 'Text START' });

  await expect(message).toBeVisible();
  await expect(link).toHaveAttribute('href', smsHref);
  await expect(message).not.toContainText('<a');

  await message.screenshot({ path: test.info().outputPath('subscription-validation-safe-link.png') });
});
