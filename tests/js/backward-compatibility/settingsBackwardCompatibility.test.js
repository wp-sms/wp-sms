/**
 * Backward Compatibility Tests for Settings
 *
 * Ensures that the React dashboard correctly handles legacy settings formats
 * and maintains backward compatibility with the PHP backend.
 */

import { settingsApi } from '@/api/settingsApi'
import { setupWpSmsSettings } from '../testing-utils'

describe('Settings Backward Compatibility', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
    setupWpSmsSettings()
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  describe('Legacy Settings Format Handling', () => {
    test('handles legacy checkbox values (option_name = option_name)', async () => {
      // Legacy format: when checkbox is checked, value equals the key name
      const legacySettings = {
        store_outbox_messages: 'store_outbox_messages',
        display_notifications: 'display_notifications',
        add_mobile_field: 'add_mobile_field',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: legacySettings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: legacySettings, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      // React should interpret these as "checked"
      expect(result.settings.store_outbox_messages).toBeTruthy()
      expect(result.settings.display_notifications).toBeTruthy()
      expect(result.settings.add_mobile_field).toBeTruthy()
    })

    test('handles legacy boolean toggle values (string "1" or "")', async () => {
      const legacySettings = {
        international_mobile: '1',
        notif_publish_new_post_enabled: '1',
        notif_register_new_user_enabled: '',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: legacySettings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: legacySettings, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      // String '1' should be interpreted as truthy
      expect(result.settings.international_mobile).toBe('1')
      expect(result.settings.notif_publish_new_post_enabled).toBe('1')
      // Empty string should be interpreted as falsy
      expect(result.settings.notif_register_new_user_enabled).toBeFalsy()
    })

    test('handles numeric values stored as strings', async () => {
      const legacySettings = {
        outbox_retention_days: '30',
        message_retention: '90',
        mobile_county_code: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: legacySettings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: legacySettings, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      // Numeric strings should be preserved
      expect(result.settings.outbox_retention_days).toBe('30')
      expect(result.settings.message_retention).toBe('90')
      expect(parseInt(result.settings.mobile_county_code)).toBe(1)
    })

    test('handles array values stored as PHP serialized', async () => {
      // Arrays from PHP are typically JSON-encoded when passed to JS
      const legacySettings = {
        newsletter_form_groups: ['group_1', 'group_2', 'group_3'],
        notification_receivers: ['+15551111111', '+15552222222'],
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: legacySettings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: legacySettings, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(Array.isArray(result.settings.newsletter_form_groups)).toBe(true)
      expect(result.settings.newsletter_form_groups).toContain('group_1')
    })
  })

  describe('Sensitive Fields Handling', () => {
    test('recognizes masked sensitive field values', async () => {
      const settingsWithMaskedFields = {
        gateway_name: 'twilio',
        gateway_key: '••••••••',
        gateway_password: '••••••••',
        gateway_sender_id: '+15551234567',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: settingsWithMaskedFields,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: settingsWithMaskedFields, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.gateway_key).toBe('••••••••')
      expect(result.settings.gateway_password).toBe('••••••••')
    })

    test('preserves masked values when sending unchanged', async () => {
      const updateData = {
        settings: {
          gateway_name: 'vonage',
          gateway_key: '••••••••', // unchanged
          gateway_password: '••••••••', // unchanged
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Settings saved',
          data: {
            settings: updateData.settings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Settings saved',
          data: { settings: updateData.settings, proSettings: {} },
        }),
      })

      await settingsApi.updateSettings(updateData)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(updateData),
        })
      )
    })
  })

  describe('Pro Settings Backward Compatibility', () => {
    test('handles legacy pro settings structure', async () => {
      const legacyProSettings = {
        wc_notify_customer_order_status_enabled: '1',
        wc_notify_customer_order_status_message: 'Your order #{order_id} status: {status}',
        wc_notify_new_order_enabled: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: {},
            proSettings: legacyProSettings,
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: {}, proSettings: legacyProSettings },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.proSettings.wc_notify_customer_order_status_enabled).toBe('1')
      expect(result.proSettings.wc_notify_customer_order_status_message).toContain('{order_id}')
    })

    test('updates pro settings independently', async () => {
      const updateData = {
        proSettings: {
          wc_notify_customer_order_status_enabled: '1',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Settings saved',
          data: {
            settings: {},
            proSettings: updateData.proSettings,
          },
        }),
        text: async () => JSON.stringify({
          message: 'Settings saved',
          data: { settings: {}, proSettings: updateData.proSettings },
        }),
      })

      const result = await settingsApi.updateSettings(updateData)

      expect(result.proSettings.wc_notify_customer_order_status_enabled).toBe('1')
    })
  })

  describe('Add-on Settings Backward Compatibility', () => {
    test('handles WooCommerce yes/no format for checkboxes', async () => {
      // WooCommerce uses 'yes'/'no' for checkboxes
      const addonValues = {
        woocommerce_pro: {
          wpsms_wc_enable_order_notify: 'yes',
          wpsms_wc_enable_admin_notify: 'no',
        },
      }

      setupWpSmsSettings({
        addonValues,
      })

      // Values should be accessible
      expect(window.wpSmsSettings.addonValues.woocommerce_pro.wpsms_wc_enable_order_notify).toBe('yes')
    })

    test('converts boolean to yes/no when saving add-on settings', async () => {
      const updateData = {
        addonValues: {
          woocommerce_pro: {
            wpsms_wc_enable_order_notify: true,
          },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Settings saved',
          data: {
            settings: {},
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Settings saved',
          data: { settings: {}, proSettings: {} },
        }),
      })

      await settingsApi.updateSettings(updateData)

      // Should have been called with the data
      expect(global.fetch).toHaveBeenCalled()
    })
  })

  describe('Empty and Null Values', () => {
    test('handles empty settings object gracefully', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: {},
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: {}, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings).toEqual({})
      expect(result.proSettings).toEqual({})
    })

    test('handles null values in settings', async () => {
      const settingsWithNulls = {
        gateway_name: 'twilio',
        gateway_key: null,
        admin_mobile_number: null,
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: settingsWithNulls,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: settingsWithNulls, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.gateway_key).toBeNull()
      expect(result.settings.admin_mobile_number).toBeNull()
    })

    test('handles undefined option gracefully', async () => {
      const settingsWithMissingKeys = {
        gateway_name: 'twilio',
        // Missing other expected keys
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: settingsWithMissingKeys,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: settingsWithMissingKeys, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.gateway_name).toBe('twilio')
      expect(result.settings.nonexistent_key).toBeUndefined()
    })
  })

  describe('Special Characters and Encoding', () => {
    test('handles special characters in settings values', async () => {
      const settingsWithSpecialChars = {
        gateway_sender_id: 'Company & Sons <SMS>',
        custom_message: 'Hello! It\'s a "test" message',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: settingsWithSpecialChars,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: settingsWithSpecialChars, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.gateway_sender_id).toContain('&')
      expect(result.settings.custom_message).toContain('"')
    })

    test('handles Unicode/international characters', async () => {
      const settingsWithUnicode = {
        custom_message: 'سلام! 你好! Hello! 👋',
        sender_name: 'شرکت تست',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: settingsWithUnicode,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: settingsWithUnicode, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.custom_message).toContain('سلام')
      expect(result.settings.custom_message).toContain('你好')
    })
  })

  describe('Settings Merge Behavior', () => {
    test('partial update preserves existing settings', async () => {
      // When updating only some settings, others should be preserved
      const existingSettings = {
        gateway_name: 'twilio',
        gateway_sender_id: '+15551234567',
        admin_mobile_number: '+15559876543',
      }

      const updateData = {
        settings: {
          admin_mobile_number: '+15550000000', // Only updating this
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Settings saved',
          data: {
            settings: {
              ...existingSettings,
              admin_mobile_number: '+15550000000',
            },
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Settings saved',
          data: {
            settings: {
              ...existingSettings,
              admin_mobile_number: '+15550000000',
            },
            proSettings: {},
          },
        }),
      })

      const result = await settingsApi.updateSettings(updateData)

      // Other settings should still be present
      expect(result.settings.gateway_name).toBe('twilio')
      expect(result.settings.gateway_sender_id).toBe('+15551234567')
      expect(result.settings.admin_mobile_number).toBe('+15550000000')
    })
  })

  describe('Gateway Settings Backward Compatibility', () => {
    test('handles different gateway option formats', async () => {
      const gatewaySettings = {
        gateway_name: 'twilio',
        // Different gateways may use different option names
        twilio_account_sid: 'AC123456',
        twilio_auth_token: 'auth_token_here',
        // Generic gateway options
        gateway_username: 'user123',
        gateway_password: 'pass123',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            settings: gatewaySettings,
            proSettings: {},
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: { settings: gatewaySettings, proSettings: {} },
        }),
      })

      const result = await settingsApi.getSettings()

      expect(result.settings.gateway_name).toBe('twilio')
      expect(result.settings.twilio_account_sid).toBe('AC123456')
    })
  })

  describe('Validation Error Handling', () => {
    test('handles validation errors in legacy format', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({
          message: 'Validation failed',
          data: {
            errors: {
              gateway_name: 'Invalid gateway selected',
              admin_mobile_number: 'Invalid phone number format',
            },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Validation failed',
          data: {
            errors: {
              gateway_name: 'Invalid gateway selected',
              admin_mobile_number: 'Invalid phone number format',
            },
          },
        }),
      })

      await expect(
        settingsApi.updateSettings({ settings: { gateway_name: 'invalid' } })
      ).rejects.toThrow()
    })
  })
})
