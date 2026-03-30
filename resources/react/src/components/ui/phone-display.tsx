import { formatPhoneDisplay } from '@/lib/phone-format';
import type { PhoneDisplayMode } from '@/lib/constants';
import { getConfig } from '@/lib/api';

interface PhoneDisplayProps {
  value: string | null | undefined;
  channel?: string;
  fallback?: string;
}

const getDisplayMode = (): PhoneDisplayMode =>
  (getConfig().phoneInput?.displayMode as PhoneDisplayMode) ?? 'international';

export function PhoneDisplay({ value, channel, fallback = '\u2014' }: PhoneDisplayProps) {
  if (!value) return <>{fallback}</>;
  if (channel && channel !== 'sms') return <>{value}</>;

  const result = formatPhoneDisplay(value, getDisplayMode());
  if (!result) return <>{value}</>;

  return (
    <span dir="ltr" className="inline-flex items-center gap-1.5 font-mono text-sm tabular-nums">
      <span aria-hidden="true">{result.flag}</span>
      {result.formatted}
    </span>
  );
}
