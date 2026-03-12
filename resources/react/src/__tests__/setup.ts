import { beforeAll, afterAll, afterEach } from 'vitest';
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { server } from './mocks/server';
import { resetMockSettings } from './mocks/handlers';

// Set up window.wpSmsSettings before any test runs
Object.defineProperty(window, 'wpSmsSettings', {
  writable: true,
  value: {
    restUrl: 'https://example.com/wp-json/wsms/v1/',
    nonce: 'test-nonce',
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
