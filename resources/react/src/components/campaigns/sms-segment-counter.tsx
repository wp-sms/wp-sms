import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from 'react';

// GSM-7 basic character set (128 chars)
const GSM7_BASIC = new Set(
  '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ ÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZ' +
  'ÄÖÑÜabcdefghijklmnopqrstuvwxyz§äöñüà'
);

// GSM-7 extension chars (count as 2 characters)
const GSM7_EXTENSION = new Set('^{}\\[~]|€');

function isGSM7(text: string): boolean {
  for (const char of text) {
    if (!GSM7_BASIC.has(char) && !GSM7_EXTENSION.has(char)) {
      return false;
    }
  }
  return true;
}

function countGSM7Chars(text: string): number {
  let count = 0;
  for (const char of text) {
    count += GSM7_EXTENSION.has(char) ? 2 : 1;
  }
  return count;
}

export interface SegmentInfo {
  encoding: 'GSM-7' | 'Unicode';
  charCount: number;
  maxCharsPerSegment: number;
  maxCharsFirst: number;
  segmentCount: number;
  nonGsmChars: string[];
}

export function calculateSegments(text: string): SegmentInfo {
  if (!text) {
    return { encoding: 'GSM-7', charCount: 0, maxCharsPerSegment: 160, maxCharsFirst: 160, segmentCount: 0, nonGsmChars: [] };
  }

  const gsm7 = isGSM7(text);
  const encoding = gsm7 ? 'GSM-7' as const : 'Unicode' as const;

  const charCount = gsm7 ? countGSM7Chars(text) : [...text].length;

  const singleLimit = gsm7 ? 160 : 70;
  const multiLimit = gsm7 ? 153 : 67;

  let segmentCount: number;
  if (charCount <= singleLimit) {
    segmentCount = charCount > 0 ? 1 : 0;
  } else {
    segmentCount = Math.ceil(charCount / multiLimit);
  }

  // Find non-GSM characters
  const nonGsmChars: string[] = [];
  if (!gsm7) {
    const seen = new Set<string>();
    for (const char of text) {
      if (!GSM7_BASIC.has(char) && !GSM7_EXTENSION.has(char) && !seen.has(char)) {
        seen.add(char);
        nonGsmChars.push(char);
      }
    }
  }

  return {
    encoding,
    charCount,
    maxCharsPerSegment: charCount <= singleLimit ? singleLimit : multiLimit,
    maxCharsFirst: singleLimit,
    segmentCount,
    nonGsmChars,
  };
}

interface SmsSegmentCounterProps {
  text: string;
  optOutText?: string;
}

export function SmsSegmentCounter({ text, optOutText }: SmsSegmentCounterProps) {
  const fullText = optOutText ? `${text}\n\n${optOutText}` : text;
  const info = useMemo(() => calculateSegments(fullText), [fullText]);

  if (!text) return null;

  return (
    <div className="flex items-center gap-3 text-xs text-muted-foreground">
      <span>
        {sprintf(__('%1$d/%2$d chars', 'wp-sms'), info.charCount, info.maxCharsPerSegment)}
      </span>
      <span>
        {sprintf(info.segmentCount === 1 ? __('%d segment', 'wp-sms') : __('%d segments', 'wp-sms'), info.segmentCount)}
      </span>
      <span className={info.encoding === 'Unicode' ? 'text-amber-600' : ''}>
        {info.encoding}
      </span>
      {info.nonGsmChars.length > 0 && (
        <span className="text-amber-600" title={`Non-GSM characters: ${info.nonGsmChars.join(' ')}`}>
          {__('Unicode:', 'wp-sms')} {info.nonGsmChars.slice(0, 5).map((c) => `"${c}"`).join(', ')}
          {info.nonGsmChars.length > 5 ? ` +${info.nonGsmChars.length - 5} more` : ''}
        </span>
      )}
    </div>
  );
}
