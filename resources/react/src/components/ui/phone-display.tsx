import { formatPhoneDisplay } from '@/lib/phone-format';
import type { PhoneDisplayMode } from '@/lib/constants';
import { getConfig } from '@/lib/api';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

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

  const mode = getDisplayMode();
  const result = formatPhoneDisplay(value, mode);
  if (!result) return <>{value}</>;

  const showFlag = mode !== 'national';

  const display = (
    <span dir="ltr" className="inline-flex items-center gap-1 font-mono text-xs tabular-nums">
      {showFlag && <span aria-hidden="true">{result.flag}</span>}
      {result.dialCode && mode === 'separate_dial_code' && (
        <span className="text-muted-foreground">{result.dialCode}</span>
      )}
      {result.formatted}
    </span>
  );

  if (result.tooltipText) {
    return (
      <Tooltip>
        <TooltipTrigger asChild>{display}</TooltipTrigger>
        <TooltipContent>{result.tooltipText}</TooltipContent>
      </Tooltip>
    );
  }

  return display;
}
