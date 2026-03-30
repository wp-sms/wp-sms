// Adding 0-9 to a block's zero code-point gives the corresponding digit character.
const DIGIT_BLOCK_ZEROS = [
    0x0660, // Arabic-Indic
    0x06F0, // Extended Arabic-Indic (Persian/Urdu)
    0x0966, // Devanagari
    0x09E6, // Bengali
    0x0A66, // Gurmukhi
    0x0AE6, // Gujarati
    0x0B66, // Oriya
    0x0BE6, // Tamil
    0x0C66, // Telugu
    0x0CE6, // Kannada
    0x0D66, // Malayalam
    0x0E50, // Thai
    0x0ED0, // Lao
    0x0F20, // Tibetan
    0x1040, // Myanmar
    0x17E0, // Khmer
    0x1810, // Mongolian
    0xFF10, // Full-width
];

const digitMap = new Map();
for (const zero of DIGIT_BLOCK_ZEROS) {
    for (let d = 0; d <= 9; d++) {
        digitMap.set(zero + d, String(d));
    }
}

export function normalizeDigits(text) {
    let result = '';
    for (const char of text) {
        const cp = char.codePointAt(0);
        if (cp >= 0x30 && cp <= 0x39) {
            result += char;
        } else {
            const ascii = digitMap.get(cp);
            result += ascii !== undefined ? ascii : char;
        }
    }
    return result;
}

export function extractDigits(text) {
    return normalizeDigits(text.replace(/[^\p{Nd}]/gu, ''));
}

export const REGEXP_UNICODE_DIGITS = /^[\p{Nd}]+$/u;
