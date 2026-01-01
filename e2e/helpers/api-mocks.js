/**
 * API Mock Responses for E2E Tests
 *
 * These mocks simulate gateway API responses for testing
 * send SMS functionality without actually sending messages.
 */

/**
 * Pre-defined mock responses for common scenarios
 */
export const mockResponses = {
  // Send SMS responses
  sendSuccess: {
    success: true,
    data: {
      message_id: 'mock-msg-123',
      credits_used: 1,
      recipients_count: 1,
    },
  },

  sendFailed: {
    success: false,
    data: {
      error: 'Gateway error: Invalid credentials',
    },
  },

  sendInsufficientCredits: {
    success: false,
    data: {
      error: 'Insufficient credits',
    },
  },

  sendInvalidNumber: {
    success: false,
    data: {
      error: 'Invalid phone number format',
    },
  },

  // Credit balance responses
  creditBalance: {
    success: true,
    data: {
      balance: 1000,
      currency: 'credits',
    },
  },

  creditBalanceLow: {
    success: true,
    data: {
      balance: 5,
      currency: 'credits',
    },
  },

  // Recipient count responses
  recipientCount: {
    success: true,
    data: {
      count: 25,
    },
  },
};

/**
 * Mock the send SMS API endpoint
 * @param {Page} page - Playwright page object
 * @param {string} responseType - Key from mockResponses or custom response object
 */
export async function mockSendSmsApi(page, responseType = 'sendSuccess') {
  const response = typeof responseType === 'string' ? mockResponses[responseType] : responseType;

  await page.route('**/wp-json/wpsms/v1/send/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });
}

/**
 * Mock the credit balance API endpoint
 * @param {Page} page - Playwright page object
 * @param {string} responseType - Key from mockResponses or custom response object
 */
export async function mockCreditApi(page, responseType = 'creditBalance') {
  const response = typeof responseType === 'string' ? mockResponses[responseType] : responseType;

  await page.route('**/wp-json/wpsms/v1/credit**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });
}

/**
 * Mock the recipient count API endpoint
 * @param {Page} page - Playwright page object
 * @param {number} count - Number of recipients to return
 */
export async function mockRecipientCountApi(page, count = 25) {
  await page.route('**/wp-json/wpsms/v1/send/count**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: { count },
      }),
    });
  });
}

/**
 * Mock all SMS-related APIs with default success responses
 * @param {Page} page - Playwright page object
 */
export async function mockAllSmsApis(page) {
  await mockSendSmsApi(page, 'sendSuccess');
  await mockCreditApi(page, 'creditBalance');
  await mockRecipientCountApi(page, 25);
}

/**
 * Clear all route mocks
 * @param {Page} page - Playwright page object
 */
export async function clearMocks(page) {
  await page.unrouteAll({ behavior: 'ignoreErrors' });
}
