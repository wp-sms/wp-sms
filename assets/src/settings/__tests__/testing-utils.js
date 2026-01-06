import React from 'react'
import { render } from '@testing-library/react'
import { SettingsProvider } from '../context/SettingsContext'
import { ThemeProvider } from '../context/ThemeContext'
import { Toaster } from '../components/ui/toaster'

/**
 * Setup default wpSmsSettings mock
 */
export function setupWpSmsSettings(overrides = {}) {
  const defaults = {
    apiUrl: 'http://localhost/wp-json/wpsms/v1/',
    nonce: 'test-nonce',
    settings: {
      gateway_name: 'twilio',
      admin_mobile_number: '+1234567890',
    },
    proSettings: {},
    gateways: {},
    addons: {
      pro: false,
    },
    features: {
      gdprEnabled: false,
      hasProAddon: false,
      twoWayEnabled: false,
      scheduledSms: false,
      isProActive: false,
      isWooActive: false,
      isBuddyPressActive: false,
    },
    licenses: [],
    licensedPluginsCount: 0,
    totalPlugins: 7,
    version: '7.0.0',
    countries: [],
    i18n: {},
    newsletter_form_verify: false,
  }

  // Extract feature flags from overrides to merge into features
  const featureFlags = ['gdprEnabled', 'hasProAddon', 'twoWayEnabled', 'scheduledSms', 'isProActive', 'isWooActive', 'isBuddyPressActive']
  const featureOverrides = {}
  featureFlags.forEach(flag => {
    if (flag in overrides) {
      featureOverrides[flag] = overrides[flag]
    }
  })

  global.window.wpSmsSettings = {
    ...defaults,
    ...overrides,
    settings: { ...defaults.settings, ...overrides.settings },
    proSettings: { ...defaults.proSettings, ...overrides.proSettings },
    addons: { ...defaults.addons, ...overrides.addons },
    features: { ...defaults.features, ...overrides.features, ...featureOverrides },
    i18n: { ...defaults.i18n, ...overrides.i18n },
  }

  return global.window.wpSmsSettings
}

/**
 * All providers wrapper for testing
 */
export function AllProviders({ children }) {
  return (
    <ThemeProvider>
      <SettingsProvider>
        <Toaster>
          {children}
        </Toaster>
      </SettingsProvider>
    </ThemeProvider>
  )
}

/**
 * Custom render with all providers
 */
export function renderWithProviders(ui, options = {}) {
  return render(ui, { wrapper: AllProviders, ...options })
}

/**
 * Create mock notification data
 */
export function createMockNotification(overrides = {}) {
  return {
    id: 'test-notification-1',
    title: 'Test Notification',
    content: '<p>Test notification content</p>',
    type: 'info',
    date: new Date().toISOString(),
    primary_cta: null,
    secondary_cta: null,
    ...overrides,
  }
}

/**
 * Create mock outbox message data
 */
export function createMockOutboxMessage(overrides = {}) {
  return {
    id: 1,
    recipient: '+1234567890',
    recipient_count: 1,
    message: 'Test message content',
    sender: 'TestSender',
    date: new Date().toISOString(),
    status: 'success',
    response: null,
    media: null,
    ...overrides,
  }
}

/**
 * Create mock subscriber data
 */
export function createMockSubscriber(overrides = {}) {
  return {
    id: 1,
    mobile: '+1234567890',
    name: 'Test User',
    group_id: '1',
    status: '1',
    country_code: 'US',
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

/**
 * Create mock group data
 */
export function createMockGroup(overrides = {}) {
  return {
    id: '1',
    name: 'Test Group',
    subscribers_count: 10,
    ...overrides,
  }
}

/**
 * Wait for debounced operations
 */
export async function waitForDebounce(ms = 300) {
  await new Promise(resolve => setTimeout(resolve, ms))
}

export { render }
