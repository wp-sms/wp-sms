import { test, expect } from '../../fixtures/test.js';

const validationMessage = [
  'You are currently unsubscribed.<br><br>',
  '<a href="sms:+15555554567?&body=START" target="_blank" rel="noopener">Text START</a>',
  '<br>Or text START to +1 (555) 555-4567',
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
  await page.evaluate(() => {
    document.body.innerHTML = `
      <form class="js-wpSmsSubscribeForm">
        <div class="js-wpSmsSubscriberName"><input type="text"></div>
        <div class="js-wpSmsSubscriberMobile"><input type="tel"></div>
        <input class="js-wpSmsSubscribeType" type="radio" value="subscribe" checked>
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
      unknown_error: 'Unknown error',
      loading_text: 'Loading...',
      subscribe_text: 'Subscribe',
    };
  });

  await page.addScriptTag({ url: '/wp-includes/js/jquery/jquery.min.js' });
  await page.addScriptTag({ url: '/wp-content/plugins/wp-sms/public/js/frontend.min.js' });

  await page.locator('.js-wpSmsSubscriberName input').fill('Browser QA');
  await page.locator('.js-wpSmsSubscriberMobile input').fill('+15555554567');
  await page.locator('.js-wpSmsSubmitButton').click();

  const message = page.locator('.wpsms-subscribe__message--error');
  const link = message.getByRole('link', { name: 'Text START' });

  await expect(message).toBeVisible();
  await expect(link).toHaveAttribute('href', 'sms:+15555554567?&body=START');
  await expect(message).not.toContainText('<a');

  await message.screenshot({ path: test.info().outputPath('subscription-validation-safe-link.png') });
});
