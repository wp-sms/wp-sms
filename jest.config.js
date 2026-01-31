module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/resources/react/src/$1',
  },
  transform: {
    '^.+\\.(js|jsx)$': 'babel-jest',
  },
  testMatch: [
    '<rootDir>/assets/src/settings/**/__tests__/**/*.{js,jsx}',
    '<rootDir>/assets/src/settings/**/*.{test,spec}.{js,jsx}',
    '<rootDir>/tests/js/**/*.{test,spec}.{js,jsx}',
  ],
  moduleFileExtensions: ['js', 'jsx', 'json'],
  collectCoverageFrom: [
    'assets/src/settings/**/*.{js,jsx}',
    'resources/react/src/**/*.{js,jsx}',
    '!assets/src/settings/**/*.test.{js,jsx}',
    '!assets/src/settings/**/index.{js,jsx}',
    '!resources/react/src/**/*.test.{js,jsx}',
    '!resources/react/src/**/index.{js,jsx}',
  ],
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  testPathIgnorePatterns: ['/node_modules/', '/assets/dist/', 'testing-utils.js'],
  transformIgnorePatterns: [
    '/node_modules/(?!(@radix-ui|lucide-react)/)',
  ],
}
