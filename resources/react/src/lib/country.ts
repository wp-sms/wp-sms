import { __ } from '@wordpress/i18n';

const SPECIAL: Record<string, string> = { T1: 'Tor', XX: __('Unknown', 'wp-sms') };

export function countryFlag(code: string): string {
  if (SPECIAL[code]) return '';
  return [...code.toUpperCase()]
    .map(c => String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65))
    .join('');
}

export function formatCountry(code: string | null | undefined): string {
  if (!code) return '\u2014';
  const special = SPECIAL[code];
  if (special) return special;
  return `${countryFlag(code)} ${code}`;
}
