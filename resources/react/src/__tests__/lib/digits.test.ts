import { describe, it, expect } from 'vitest';
import { normalizeDigits, extractDigits, REGEXP_UNICODE_DIGITS } from '@preact/utils/digits';

describe('normalizeDigits', () => {
    it('passes ASCII digits through unchanged', () => {
        expect(normalizeDigits('123456')).toBe('123456');
    });

    it('normalizes Persian digits', () => {
        expect(normalizeDigits('۴۸۳۹۲۱')).toBe('483921');
    });

    it('normalizes Arabic-Indic digits', () => {
        expect(normalizeDigits('٤٨٣٩٢١')).toBe('483921');
    });

    it('normalizes Devanagari digits', () => {
        expect(normalizeDigits('४८३९२१')).toBe('483921');
    });

    it('normalizes Thai digits', () => {
        expect(normalizeDigits('๔๘๓๙๒๑')).toBe('483921');
    });

    it('normalizes full-width digits', () => {
        expect(normalizeDigits('４８３９２１')).toBe('483921');
    });

    it('normalizes mixed scripts', () => {
        expect(normalizeDigits('12۳۴56')).toBe('123456');
    });

    it('returns empty string for empty input', () => {
        expect(normalizeDigits('')).toBe('');
    });

    it('leaves non-digit characters unchanged', () => {
        expect(normalizeDigits('abc')).toBe('abc');
    });
});

describe('extractDigits', () => {
    it('strips non-digits and normalizes', () => {
        expect(extractDigits('code: ۴۸۳ ۹۲۱')).toBe('483921');
    });

    it('strips non-digits from ASCII input', () => {
        expect(extractDigits('code: 483 921')).toBe('483921');
    });

    it('handles empty string', () => {
        expect(extractDigits('')).toBe('');
    });

    it('returns empty for string with no digits', () => {
        expect(extractDigits('no digits here!')).toBe('');
    });
});

describe('REGEXP_UNICODE_DIGITS', () => {
    it('matches ASCII digits', () => {
        expect(REGEXP_UNICODE_DIGITS.test('123456')).toBe(true);
    });

    it('matches Persian digits', () => {
        expect(REGEXP_UNICODE_DIGITS.test('۴۸۳۹۲۱')).toBe(true);
    });

    it('matches Arabic-Indic digits', () => {
        expect(REGEXP_UNICODE_DIGITS.test('٤٨٣٩٢١')).toBe(true);
    });

    it('rejects non-digit characters', () => {
        expect(REGEXP_UNICODE_DIGITS.test('abc')).toBe(false);
    });

    it('rejects mixed digits and non-digits', () => {
        expect(REGEXP_UNICODE_DIGITS.test('12 34')).toBe(false);
    });

    it('rejects empty string', () => {
        expect(REGEXP_UNICODE_DIGITS.test('')).toBe(false);
    });
});
