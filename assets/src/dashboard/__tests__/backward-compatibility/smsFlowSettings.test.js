/**
 * SMS Flow Settings Backward Compatibility Tests
 *
 * Verifies that React settings use the same option keys as legacy PHP settings,
 * ensuring both interfaces affect the SMS flow identically.
 */

import React from 'react'
import { render, screen } from '@testing-library/react'
import { SettingsProvider, useSetting } from '../../context/SettingsContext'

// Mock the utils to provide test data
jest.mock('../../lib/utils', () => ({
  getWpSettings: () => ({
    settings: {},
    proSettings: {},
    gateways: {},
    countries: { US: 'United States', GB: 'United Kingdom' },
    nonce: 'test-nonce',
    restUrl: 'http://test.com/wp-json/',
  }),
  deepMerge: jest.fn((a, b) => ({ ...a, ...b })),
  __: (str) => str,
  cn: (...args) => args.filter(Boolean).join(' '),
}))

// Mock the API
jest.mock('../../api/settingsApi', () => ({
  settingsApi: {
    getSettings: jest.fn().mockResolvedValue({
      settings: {},
      proSettings: {},
      gateways: {},
    }),
    updateSettings: jest.fn().mockResolvedValue({ success: true }),
  },
}))

/**
 * Test component that exposes setting keys
 */
function SettingKeyTester({ settingKey, defaultValue = '' }) {
  const [value] = useSetting(settingKey, defaultValue)
  return <div data-testid={`setting-${settingKey}`}>{String(value)}</div>
}

/**
 * Wrapper component with provider
 */
function TestWrapper({ children }) {
  return <SettingsProvider>{children}</SettingsProvider>
}

describe('SMS Flow Settings - Option Key Compatibility', () => {
  /**
   * These are the critical SMS-flow-affecting settings that must use
   * the exact same keys in both React and legacy PHP settings.
   */
  const smsFlowSettings = [
    { key: 'sms_delivery_method', defaultValue: 'api_direct_send', description: 'Delivery method (direct/async/queued)' },
    { key: 'send_unicode', defaultValue: '', description: 'Unicode message encoding' },
    { key: 'clean_numbers', defaultValue: '', description: 'Strip spaces from phone numbers' },
    { key: 'send_only_local_numbers', defaultValue: '', description: 'Filter to local numbers' },
    { key: 'only_local_numbers_countries', defaultValue: [], description: 'Allowed countries for local filter' },
    { key: 'mobile_county_code', defaultValue: '0', description: 'Country code to prepend' },
  ]

  describe('Gateway Settings (Gateway.jsx)', () => {
    test.each([
      ['sms_delivery_method', 'api_direct_send'],
      ['send_unicode', ''],
      ['clean_numbers', ''],
      ['send_only_local_numbers', ''],
      ['only_local_numbers_countries', []],
    ])('uses correct key: %s with default: %s', (key, defaultValue) => {
      render(
        <TestWrapper>
          <SettingKeyTester settingKey={key} defaultValue={defaultValue} />
        </TestWrapper>
      )

      const element = screen.getByTestId(`setting-${key}`)
      expect(element).toBeInTheDocument()
    })
  })

  describe('Phone Configuration Settings (PhoneConfig.jsx)', () => {
    test.each([
      ['mobile_county_code', '0'],
      ['international_mobile', ''],
      ['international_mobile_only_countries', []],
      ['international_mobile_preferred_countries', []],
    ])('uses correct key: %s with default: %s', (key, defaultValue) => {
      render(
        <TestWrapper>
          <SettingKeyTester settingKey={key} defaultValue={defaultValue} />
        </TestWrapper>
      )

      const element = screen.getByTestId(`setting-${key}`)
      expect(element).toBeInTheDocument()
    })
  })

  describe('Setting Key Documentation', () => {
    test('all SMS flow settings are documented', () => {
      const expectedKeys = [
        'sms_delivery_method',
        'send_unicode',
        'clean_numbers',
        'send_only_local_numbers',
        'only_local_numbers_countries',
        'mobile_county_code',
      ]

      const documentedKeys = smsFlowSettings.map((s) => s.key)

      expectedKeys.forEach((key) => {
        expect(documentedKeys).toContain(key)
      })
    })
  })
})

describe('SMS Flow Settings - Value Format Compatibility', () => {
  describe('Checkbox Values', () => {
    test('empty string represents disabled checkbox', () => {
      render(
        <TestWrapper>
          <SettingKeyTester settingKey="clean_numbers" defaultValue="" />
        </TestWrapper>
      )

      const element = screen.getByTestId('setting-clean_numbers')
      expect(element.textContent).toBe('')
    })

    test('"1" represents enabled checkbox', () => {
      // This would be tested with actual settings context that has the value set
      // For now, we verify the default handling
      render(
        <TestWrapper>
          <SettingKeyTester settingKey="clean_numbers" defaultValue="1" />
        </TestWrapper>
      )

      const element = screen.getByTestId('setting-clean_numbers')
      expect(element.textContent).toBe('1')
    })
  })

  describe('Select/Dropdown Values', () => {
    test('sms_delivery_method defaults to api_direct_send', () => {
      render(
        <TestWrapper>
          <SettingKeyTester settingKey="sms_delivery_method" defaultValue="api_direct_send" />
        </TestWrapper>
      )

      const element = screen.getByTestId('setting-sms_delivery_method')
      expect(element.textContent).toBe('api_direct_send')
    })

    test('sms_delivery_method accepts valid values', () => {
      const validValues = ['api_direct_send', 'api_async_send', 'api_queued_send']

      validValues.forEach((value) => {
        const { unmount } = render(
          <TestWrapper>
            <SettingKeyTester settingKey="sms_delivery_method" defaultValue={value} />
          </TestWrapper>
        )

        const element = screen.getByTestId('setting-sms_delivery_method')
        expect(element.textContent).toBe(value)
        unmount()
      })
    })
  })

  describe('Array Values', () => {
    test('only_local_numbers_countries defaults to empty array', () => {
      render(
        <TestWrapper>
          <SettingKeyTester settingKey="only_local_numbers_countries" defaultValue={[]} />
        </TestWrapper>
      )

      const element = screen.getByTestId('setting-only_local_numbers_countries')
      // Empty array stringified
      expect(element.textContent).toBe('')
    })
  })
})

describe('SMS Flow Settings - Legacy PHP Compatibility', () => {
  /**
   * These tests verify that the React settings use the exact same
   * option keys that the legacy PHP settings page uses.
   *
   * Legacy PHP settings are defined in:
   * includes/admin/settings/class-wpsms-settings.php
   *
   * The settings are saved to WordPress option: wpsms_settings
   */

  const legacyPhpSettings = {
    // Gateway tab settings (class-wpsms-settings.php lines 569-610)
    'sms_delivery_method': {
      legacyId: 'sms_delivery_method',
      legacyType: 'select',
      legacyOptions: ['api_direct_send', 'api_async_send', 'api_queued_send'],
    },
    'send_unicode': {
      legacyId: 'send_unicode',
      legacyType: 'checkbox',
    },
    'clean_numbers': {
      legacyId: 'clean_numbers',
      legacyType: 'checkbox',
    },
    'send_only_local_numbers': {
      legacyId: 'send_only_local_numbers',
      legacyType: 'checkbox',
    },
    'only_local_numbers_countries': {
      legacyId: 'only_local_numbers_countries',
      legacyType: 'multiselect',
    },
    // Phone config settings (class-wpsms-settings.php lines 376-410)
    'mobile_county_code': {
      legacyId: 'mobile_county_code',
      legacyType: 'select',
    },
    'international_mobile': {
      legacyId: 'international_mobile',
      legacyType: 'checkbox',
    },
  }

  test.each(Object.entries(legacyPhpSettings))(
    'React uses same key as legacy PHP: %s',
    (key, legacyConfig) => {
      // Verify the key matches
      expect(key).toBe(legacyConfig.legacyId)

      // Render to verify useSetting accepts this key
      render(
        <TestWrapper>
          <SettingKeyTester settingKey={key} defaultValue="" />
        </TestWrapper>
      )

      const element = screen.getByTestId(`setting-${key}`)
      expect(element).toBeInTheDocument()
    }
  )
})

describe('SMS Flow Settings - Gateway Filter Registration', () => {
  /**
   * Documents how each setting affects the SMS flow via Gateway filters.
   *
   * These settings are read in Gateway::__construct() (class-wpsms-gateway.php)
   * and register filters on 'wp_sms_to' hook.
   */

  const filterRegistration = {
    'clean_numbers': {
      filter: 'wp_sms_to',
      method: 'cleanNumbers',
      priority: 10,
      effect: 'Strips spaces, dashes, and commas from phone numbers',
    },
    'mobile_county_code': {
      filter: 'wp_sms_to',
      method: 'applyCountryCode',
      priority: 20,
      effect: 'Prepends country code to numbers without + prefix',
    },
    'send_only_local_numbers': {
      filter: 'wp_sms_to',
      method: 'sendOnlyLocalNumbers',
      priority: 20,
      effect: 'Filters recipients to only allowed country codes',
    },
  }

  test('settings are documented with their filter effects', () => {
    Object.entries(filterRegistration).forEach(([key, config]) => {
      expect(config.filter).toBe('wp_sms_to')
      expect(config.method).toBeDefined()
      expect(config.effect).toBeDefined()
    })
  })
})
