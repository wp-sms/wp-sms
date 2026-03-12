import { defineConfig } from 'vitest/config';
import { resolve } from 'path';

export default defineConfig({
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/react/src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    root: 'resources/react/src',
    setupFiles: ['./__tests__/setup.ts'],
    include: ['__tests__/**/*.test.{ts,tsx}'],
    css: false,
  },
});
