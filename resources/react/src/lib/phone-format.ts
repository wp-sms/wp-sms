import {
  countries,
  getCountryByDialCode,
  getFlag,
  formatPhone,
  type Country,
} from 'lite-phone-input';
import type { PhoneDisplayMode } from '@/lib/constants';

export interface FormattedPhone {
  formatted: string;
  flag: string;
}

const E164_PATTERN = /^\+[1-9]\d{1,14}$/;

export function resolveCountry(e164: string): Country | undefined {
  const digits = e164.slice(1);

  for (let len = Math.min(4, digits.length); len >= 1; len--) {
    const match = getCountryByDialCode(countries, digits.slice(0, len));
    if (match) return match;
  }
  return undefined;
}

export function formatPhoneDisplay(
  value: string | null | undefined,
  mode: PhoneDisplayMode,
): FormattedPhone | null {
  if (!value) return null;
  if (!E164_PATTERN.test(value)) return null;

  const country = resolveCountry(value);
  if (!country) return null;

  const national = value.slice(1 + country.dialCode.length);
  const formatted = formatPhone(national, country.format);
  const flag = getFlag(country.code);

  let display: string;
  if (mode === 'national') {
    if (country.displayNationalPrefix && country.nationalPrefix) {
      display = country.nationalPrefix + formatted;
    } else {
      display = formatted;
    }
  } else {
    display = `+${country.dialCode} ${formatted}`;
  }

  return { formatted: display, flag };
}
