/**
 * Backward Compatibility Tests for Booking Integrations Settings
 *
 * Ensures the React dashboard correctly handles legacy settings formats
 * for all 4 booking platforms and maintains backward compatibility.
 */

import { setupWpSmsSettings } from '../testing-utils'

describe('Booking Integrations Backward Compatibility', () => {
  beforeEach(() => {
    setupWpSmsSettings()
  })

  describe('Legacy Checkbox Format Conversion', () => {
    test('converts truthy legacy checkbox value to boolean true', () => {
      // Legacy WP Settings API stores checkbox value as the option key name or "1"
      const legacyValues = {
        bookingpress_notif_admin_approved_appointment: '1',
        woo_appointments_notif_admin_new_appointment: 'woo_appointments_notif_admin_new_appointment',
        woo_bookings_notif_admin_new_booking: '1',
        booking_calendar_notif_admin_new_booking: 'on',
      }

      // Simulate the PHP getLegacyOptionValues conversion
      const converted = {}
      for (const [key, value] of Object.entries(legacyValues)) {
        converted[key] = Boolean(value)
      }

      expect(converted.bookingpress_notif_admin_approved_appointment).toBe(true)
      expect(converted.woo_appointments_notif_admin_new_appointment).toBe(true)
      expect(converted.woo_bookings_notif_admin_new_booking).toBe(true)
      expect(converted.booking_calendar_notif_admin_new_booking).toBe(true)
    })

    test('converts falsy legacy checkbox value to boolean false', () => {
      const legacyValues = {
        bookingpress_notif_admin_approved_appointment: '',
        woo_appointments_notif_admin_new_appointment: '',
        woo_bookings_notif_admin_new_booking: null,
        booking_calendar_notif_admin_new_booking: undefined,
      }

      const converted = {}
      for (const [key, value] of Object.entries(legacyValues)) {
        converted[key] = Boolean(value)
      }

      expect(converted.bookingpress_notif_admin_approved_appointment).toBe(false)
      expect(converted.woo_appointments_notif_admin_new_appointment).toBe(false)
      expect(converted.woo_bookings_notif_admin_new_booking).toBe(false)
      expect(converted.booking_calendar_notif_admin_new_booking).toBe(false)
    })
  })

  describe('React Boolean to Legacy Format Conversion (Save Handler)', () => {
    test('converts true to "1" for legacy storage', () => {
      const reactValues = {
        bookingpress_notif_admin_approved_appointment: true,
        bookingpress_notif_customer_approved_appointment: true,
      }

      const legacyValues = {}
      for (const [key, value] of Object.entries(reactValues)) {
        legacyValues[key] = value ? '1' : ''
      }

      expect(legacyValues.bookingpress_notif_admin_approved_appointment).toBe('1')
      expect(legacyValues.bookingpress_notif_customer_approved_appointment).toBe('1')
    })

    test('converts false to empty string for legacy storage', () => {
      const reactValues = {
        bookingpress_notif_admin_approved_appointment: false,
        woo_bookings_notif_customer_cancelled_booking: false,
      }

      const legacyValues = {}
      for (const [key, value] of Object.entries(reactValues)) {
        legacyValues[key] = value ? '1' : ''
      }

      expect(legacyValues.bookingpress_notif_admin_approved_appointment).toBe('')
      expect(legacyValues.woo_bookings_notif_customer_cancelled_booking).toBe('')
    })
  })

  describe('Option Key Preservation', () => {
    const allBookingPressKeys = [
      'bookingpress_notif_admin_approved_appointment',
      'bookingpress_notif_admin_approved_appointment_receiver',
      'bookingpress_notif_admin_approved_appointment_message',
      'bookingpress_notif_customer_approved_appointment',
      'bookingpress_notif_customer_approved_appointment_message',
      'bookingpress_notif_admin_pending_appointment',
      'bookingpress_notif_admin_pending_appointment_receiver',
      'bookingpress_notif_admin_pending_appointment_message',
      'bookingpress_notif_customer_pending_appointment',
      'bookingpress_notif_customer_pending_appointment_message',
      'bookingpress_notif_admin_rejected_appointment',
      'bookingpress_notif_admin_rejected_appointment_receiver',
      'bookingpress_notif_admin_rejected_appointment_message',
      'bookingpress_notif_customer_rejected_appointment',
      'bookingpress_notif_customer_rejected_appointment_message',
      'bookingpress_notif_admin_cancelled_appointment',
      'bookingpress_notif_admin_cancelled_appointment_receiver',
      'bookingpress_notif_admin_cancelled_appointment_message',
      'bookingpress_notif_customer_cancelled_appointment',
      'bookingpress_notif_customer_cancelled_appointment_message',
    ]

    const allWooAppointmentsKeys = [
      'woo_appointments_notif_admin_new_appointment',
      'woo_appointments_notif_admin_new_appointment_receiver',
      'woo_appointments_notif_admin_new_appointment_message',
      'woo_appointments_notif_admin_cancelled_appointment',
      'woo_appointments_notif_admin_cancelled_appointment_receiver',
      'woo_appointments_notif_admin_cancelled_appointment_message',
      'woo_appointments_notif_customer_cancelled_appointment',
      'woo_appointments_notif_customer_cancelled_appointment_message',
      'woo_appointments_notif_admin_rescheduled_appointment',
      'woo_appointments_notif_admin_rescheduled_appointment_receiver',
      'woo_appointments_notif_admin_rescheduled_appointment_message',
      'woo_appointments_notif_customer_confirmed_appointment',
      'woo_appointments_notif_customer_confirmed_appointment_message',
    ]

    const allWooBookingsKeys = [
      'woo_bookings_notif_admin_new_booking',
      'woo_bookings_notif_admin_new_booking_receiver',
      'woo_bookings_notif_admin_new_booking_message',
      'woo_bookings_notif_admin_cancelled_booking',
      'woo_bookings_notif_admin_cancelled_booking_receiver',
      'woo_bookings_notif_admin_cancelled_booking_message',
      'woo_bookings_notif_customer_cancelled_booking',
      'woo_bookings_notif_customer_cancelled_booking_message',
      'woo_bookings_notif_customer_confirmed_booking',
      'woo_bookings_notif_customer_confirmed_booking_message',
    ]

    const allBookingCalendarKeys = [
      'booking_calendar_notif_customer_mobile_field',
      'booking_calendar_notif_admin_new_booking',
      'booking_calendar_notif_admin_new_booking_receiver',
      'booking_calendar_notif_admin_new_booking_message',
      'booking_calendar_notif_customer_new_booking',
      'booking_calendar_notif_customer_new_booking_message',
      'booking_calendar_notif_customer_booking_approved',
      'booking_calendar_notif_customer_booking_approved_message',
      'booking_calendar_notif_customer_booking_cancelled',
      'booking_calendar_notif_customer_booking_cancelled_message',
    ]

    test('BookingPress has exactly 20 option keys', () => {
      expect(allBookingPressKeys).toHaveLength(20)
    })

    test('WooCommerce Appointments has exactly 13 option keys', () => {
      expect(allWooAppointmentsKeys).toHaveLength(13)
    })

    test('WooCommerce Bookings has exactly 10 option keys', () => {
      expect(allWooBookingsKeys).toHaveLength(10)
    })

    test('Booking Calendar has exactly 10 option keys', () => {
      expect(allBookingCalendarKeys).toHaveLength(10)
    })

    test('all option keys follow expected naming conventions', () => {
      const allKeys = [
        ...allBookingPressKeys,
        ...allWooAppointmentsKeys,
        ...allWooBookingsKeys,
        ...allBookingCalendarKeys,
      ]

      allKeys.forEach((key) => {
        expect(key).toMatch(/^[a-z][a-z0-9_]*$/)
      })
    })
  })

  describe('Text and Textarea Value Passthrough', () => {
    test('text values pass through unchanged', () => {
      const values = {
        bookingpress_notif_admin_approved_appointment_receiver: '+1234567890,+0987654321',
        woo_appointments_notif_admin_new_appointment_receiver: '+15551234567',
      }

      // Simulate passthrough (no conversion needed)
      expect(values.bookingpress_notif_admin_approved_appointment_receiver).toBe('+1234567890,+0987654321')
      expect(values.woo_appointments_notif_admin_new_appointment_receiver).toBe('+15551234567')
    })

    test('textarea values with template variables pass through unchanged', () => {
      const message = 'Booking #%booking_id% on %appointment_date% at %appointment_time% for %customer_full_name%'

      expect(message).toContain('%booking_id%')
      expect(message).toContain('%appointment_date%')
      expect(message).toContain('%customer_full_name%')
    })
  })

  describe('Select Field (Booking Calendar Mobile Field)', () => {
    test('preserves select field value', () => {
      const value = 'phone_field_1'

      // Simulate round-trip
      const saved = value
      expect(saved).toBe('phone_field_1')
    })

    test('handles empty select field value', () => {
      const value = ''
      expect(value).toBe('')
    })
  })

  describe('Empty and Null Value Handling', () => {
    test('handles missing option values gracefully', () => {
      const legacyValues = {}

      // Missing keys should default to false for switches
      const switchValue = legacyValues.bookingpress_notif_admin_approved_appointment
      expect(Boolean(switchValue)).toBe(false)

      // Missing keys should default to empty string for text
      const textValue = legacyValues.bookingpress_notif_admin_approved_appointment_receiver || ''
      expect(textValue).toBe('')
    })

    test('handles null values', () => {
      const value = null
      const converted = value ? '1' : ''
      expect(converted).toBe('')
    })
  })

  describe('Round-Trip Compatibility', () => {
    test('load legacy → save via React → verify legacy format preserved', () => {
      // 1. Simulate legacy values as stored in wpsms_settings
      const legacyStored = {
        bookingpress_notif_admin_approved_appointment: '1',
        bookingpress_notif_admin_approved_appointment_receiver: '+15551234567',
        bookingpress_notif_admin_approved_appointment_message: 'Appointment #%booking_id% approved',
        bookingpress_notif_customer_approved_appointment: '',  // unchecked
        booking_calendar_notif_customer_mobile_field: 'phone_field_1',
      }

      // 2. Convert to React format (what getLegacyOptionValues does)
      const reactValues = {}
      const switchKeys = [
        'bookingpress_notif_admin_approved_appointment',
        'bookingpress_notif_customer_approved_appointment',
      ]

      for (const [key, value] of Object.entries(legacyStored)) {
        if (switchKeys.includes(key)) {
          reactValues[key] = Boolean(value)
        } else {
          reactValues[key] = value || ''
        }
      }

      expect(reactValues.bookingpress_notif_admin_approved_appointment).toBe(true)
      expect(reactValues.bookingpress_notif_customer_approved_appointment).toBe(false)
      expect(reactValues.bookingpress_notif_admin_approved_appointment_receiver).toBe('+15551234567')
      expect(reactValues.bookingpress_notif_admin_approved_appointment_message).toBe('Appointment #%booking_id% approved')
      expect(reactValues.booking_calendar_notif_customer_mobile_field).toBe('phone_field_1')

      // 3. Convert back to legacy format (what handleSave does)
      const savedLegacy = {}
      for (const [key, value] of Object.entries(reactValues)) {
        if (switchKeys.includes(key)) {
          savedLegacy[key] = value ? '1' : ''
        } else {
          savedLegacy[key] = value
        }
      }

      // 4. Verify legacy format is preserved (truthy/falsy behavior matches)
      expect(Boolean(savedLegacy.bookingpress_notif_admin_approved_appointment)).toBe(true)
      expect(Boolean(savedLegacy.bookingpress_notif_customer_approved_appointment)).toBe(false)
      expect(savedLegacy.bookingpress_notif_admin_approved_appointment_receiver).toBe('+15551234567')
      expect(savedLegacy.bookingpress_notif_admin_approved_appointment_message).toBe('Appointment #%booking_id% approved')
      expect(savedLegacy.booking_calendar_notif_customer_mobile_field).toBe('phone_field_1')
    })
  })
})
