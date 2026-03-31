import { describe, it, expect } from 'vitest';
import { formatPhoneDisplay, resolveCountry } from '@/lib/phone-format';

describe('resolveCountry', () => {
  it('resolves US number', () => {
    const country = resolveCountry('+12025551234');
    expect(country).toBeDefined();
    expect(country!.code).toBe('US');
    expect(country!.dialCode).toBe('1');
  });

  it('resolves UK number', () => {
    const country = resolveCountry('+442079460958');
    expect(country).toBeDefined();
    expect(country!.code).toBe('GB');
  });

  it('resolves DE number', () => {
    const country = resolveCountry('+492012345678');
    expect(country).toBeDefined();
    expect(country!.code).toBe('DE');
  });

  it('resolves RU (+7) as primary country', () => {
    expect(resolveCountry('+79161234567')!.code).toBe('RU');
  });

  it('resolves +1 as US (primary)', () => {
    expect(resolveCountry('+16135551234')!.code).toBe('US');
  });
});

describe('formatPhoneDisplay', () => {
  describe('international mode', () => {
    it('formats US number', () => {
      const result = formatPhoneDisplay('+12025551234', 'international');
      expect(result).not.toBeNull();
      expect(result!.flag).toBeTruthy();
      expect(result!.formatted).toMatch(/^\+1\s/);
    });

    it('formats UK number', () => {
      const result = formatPhoneDisplay('+442079460958', 'international');
      expect(result).not.toBeNull();
      expect(result!.formatted).toMatch(/^\+44\s/);
    });

    it('formats DE number', () => {
      const result = formatPhoneDisplay('+492012345678', 'international');
      expect(result).not.toBeNull();
      expect(result!.formatted).toMatch(/^\+49\s/);
    });
  });

  describe('separate_dial_code mode', () => {
    it('returns national part only in formatted', () => {
      const result = formatPhoneDisplay('+12025551234', 'separate_dial_code');
      expect(result).not.toBeNull();
      expect(result!.formatted).not.toMatch(/^\+/);
      expect(result!.dialCode).toBe('+1');
    });

    it('returns dial code for UK number', () => {
      const result = formatPhoneDisplay('+442079460958', 'separate_dial_code');
      expect(result).not.toBeNull();
      expect(result!.dialCode).toBe('+44');
      expect(result!.formatted).not.toMatch(/^\+/);
    });
  });

  describe('dialCode field', () => {
    it('is present for international mode', () => {
      const result = formatPhoneDisplay('+12025551234', 'international');
      expect(result!.dialCode).toBe('+1');
    });

    it('is present for separate_dial_code mode', () => {
      const result = formatPhoneDisplay('+492012345678', 'separate_dial_code');
      expect(result!.dialCode).toBe('+49');
    });

    it('is absent for national mode', () => {
      const result = formatPhoneDisplay('+12025551234', 'national');
      expect(result!.dialCode).toBeUndefined();
    });
  });

  describe('tooltipText field', () => {
    it('is present for national mode with international format', () => {
      const result = formatPhoneDisplay('+442079460958', 'national');
      expect(result!.tooltipText).toMatch(/^\+44\s/);
    });

    it('is absent for international mode', () => {
      const result = formatPhoneDisplay('+12025551234', 'international');
      expect(result!.tooltipText).toBeUndefined();
    });

    it('is absent for separate_dial_code mode', () => {
      const result = formatPhoneDisplay('+12025551234', 'separate_dial_code');
      expect(result!.tooltipText).toBeUndefined();
    });
  });

  describe('national mode', () => {
    it('formats UK number with national prefix', () => {
      const result = formatPhoneDisplay('+442079460958', 'national');
      expect(result).not.toBeNull();
      expect(result!.formatted).toMatch(/^0/);
      expect(result!.formatted).not.toMatch(/^\+/);
    });

    it('formats US number without dial code', () => {
      const result = formatPhoneDisplay('+12025551234', 'national');
      expect(result).not.toBeNull();
      expect(result!.formatted).not.toMatch(/^\+/);
    });
  });

  describe('null/invalid inputs', () => {
    it('returns null for null', () => {
      expect(formatPhoneDisplay(null, 'international')).toBeNull();
    });

    it('returns null for undefined', () => {
      expect(formatPhoneDisplay(undefined, 'international')).toBeNull();
    });

    it('returns null for empty string', () => {
      expect(formatPhoneDisplay('', 'international')).toBeNull();
    });

    it('returns null for email address', () => {
      expect(formatPhoneDisplay('user@example.com', 'international')).toBeNull();
    });

    it('returns null for number without +', () => {
      expect(formatPhoneDisplay('1234567890', 'international')).toBeNull();
    });

    it('returns null for Telegram ID', () => {
      expect(formatPhoneDisplay('123456789', 'international')).toBeNull();
    });

    it('returns null for webhook URL', () => {
      expect(formatPhoneDisplay('https://example.com/webhook', 'international')).toBeNull();
    });

    it('returns null for + alone', () => {
      expect(formatPhoneDisplay('+', 'international')).toBeNull();
    });

    it('returns null for +0 (invalid E.164)', () => {
      expect(formatPhoneDisplay('+01234567890', 'international')).toBeNull();
    });
  });

  describe('boundary lengths', () => {
    it('handles minimum length E.164 without crashing', () => {
      expect(() => formatPhoneDisplay('+11', 'international')).not.toThrow();
    });

    it('handles maximum length E.164 without crashing', () => {
      expect(() => formatPhoneDisplay('+123456789012345', 'international')).not.toThrow();
    });

    it('returns null for too-long E.164', () => {
      expect(formatPhoneDisplay('+1234567890123456', 'international')).toBeNull();
    });
  });
});
