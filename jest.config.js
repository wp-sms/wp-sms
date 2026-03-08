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
    '<rootDir>/tests/js/**/*.{test,spec}.{js,jsx}',
  ],
  moduleFileExtensions: ['js', 'jsx', 'json'],
  collectCoverageFrom: [
    'resources/react/src/**/*.{js,jsx}',
    '!resources/react/src/**/*.test.{js,jsx}',
    '!resources/react/src/**/index.{js,jsx}',
  ],
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  testPathIgnorePatterns: ['/node_modules/', '/public/dashboard/', 'testing-utils.js'],
  transformIgnorePatterns: [
    '/node_modules/(?!(@radix-ui|lucide-react)/)',
  ],
}
