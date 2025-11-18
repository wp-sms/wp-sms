import { setLocaleData } from '@wordpress/i18n'

declare global {
  interface Window {
    WP_SMS_TRANSLATIONS?: Record<string, unknown>
  }
}

/**
 * Load translations from window object into @wordpress/i18n
 * This must be called before any component renders
 */
export function loadTranslations(): void {
  if (window.WP_SMS_TRANSLATIONS) {
    setLocaleData(window.WP_SMS_TRANSLATIONS, 'wp-sms')
  }
}
