import { beforeAll, afterAll, afterEach, expect } from 'vitest';
import '@testing-library/jest-dom/vitest';
import * as matchers from 'vitest-axe/matchers';
import { cleanup } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

expect.extend(matchers);
import { server } from './mocks/server';
import { resetMockSettings } from './mocks/handlers';

// Set up window.wpSmsSettings before any test runs
Object.defineProperty(window, 'wpSmsSettings', {
  writable: true,
  value: {
    version: '8.0.0',
    adminUrl: 'https://example.com/wp-admin/',
    isPremium: false,
    roles: {
      administrator: 'Administrator',
      editor: 'Editor',
      author: 'Author',
      contributor: 'Contributor',
      subscriber: 'Subscriber',
    },
  },
});

// Mirror the WP-core inline configuration of wp.apiFetch so the shared REST
// client can resolve absolute URLs (MSW intercepts against this root).
apiFetch.use(apiFetch.createRootURLMiddleware('https://example.com/wp-json/'));
apiFetch.use(apiFetch.createNonceMiddleware('test-nonce'));
(window as unknown as { wp: { apiFetch: typeof apiFetch } }).wp = { apiFetch };

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

afterEach(() => {
  server.resetHandlers();
  resetMockSettings();
  cleanup();
});

afterAll(() => {
  server.close();
});
