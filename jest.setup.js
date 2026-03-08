import '@testing-library/jest-dom'

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(), // deprecated
    removeListener: jest.fn(), // deprecated
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
})

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
}
Object.defineProperty(window, 'localStorage', {
  value: localStorageMock,
})

// Mock window.wpSmsSettings for tests
global.window.wpSmsSettings = {
  apiUrl: 'http://localhost/wp-json/wpsms/v1/',
  nonce: 'test-nonce',
  settings: {},
  proSettings: {},
  gateways: {},
  addons: {},
  countries: {},
  postTypes: {},
  taxonomies: {},
  roles: {},
  groups: {},
  i18n: {
    save: 'Save Changes',
    saving: 'Saving...',
    saved: 'Changes saved',
    error: 'Error saving changes',
    unsavedChanges: 'You have unsaved changes',
    discard: 'Discard',
    cancel: 'Cancel',
    confirm: 'Confirm',
    loading: 'Loading...',
    search: 'Search...',
    noResults: 'No results found',
    required: 'This field is required',
    invalid: 'Invalid value',
  },
}

// Mock fetch globally
global.fetch = jest.fn()

// Reset mocks between tests
beforeEach(() => {
  jest.clearAllMocks()
})
