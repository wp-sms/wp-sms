import '@testing-library/jest-dom'

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
