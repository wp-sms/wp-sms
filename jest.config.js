module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/assets/src/dashboard/$1',
  },
  transform: {
    '^.+\\.(js|jsx)$': 'babel-jest',
  },
  testMatch: [
    '<rootDir>/assets/src/settings/**/__tests__/**/*.{js,jsx}',
    '<rootDir>/assets/src/settings/**/*.{test,spec}.{js,jsx}',
    '<rootDir>/assets/src/dashboard/**/__tests__/**/*.{js,jsx}',
    '<rootDir>/assets/src/dashboard/**/*.{test,spec}.{js,jsx}',
  ],
  moduleFileExtensions: ['js', 'jsx', 'json'],
  collectCoverageFrom: [
    'assets/src/settings/**/*.{js,jsx}',
    'assets/src/dashboard/**/*.{js,jsx}',
    '!assets/src/settings/**/*.test.{js,jsx}',
    '!assets/src/settings/**/index.{js,jsx}',
    '!assets/src/dashboard/**/*.test.{js,jsx}',
    '!assets/src/dashboard/**/index.{js,jsx}',
  ],
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  testPathIgnorePatterns: ['/node_modules/', '/assets/dist/', 'testing-utils.js'],
  transformIgnorePatterns: [
    '/node_modules/(?!(@radix-ui|lucide-react)/)',
  ],
}
