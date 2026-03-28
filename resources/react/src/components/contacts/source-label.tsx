import { __ } from '@wordpress/i18n';
import { formatSource } from '@/lib/constants';

interface SourceLabelProps {
  source: string;
  sourceRef?: string | null;
  showPrefix?: boolean;
}

export function SourceLabel({ source, sourceRef, showPrefix = true }: SourceLabelProps) {
  const { label, detail } = formatSource(source, sourceRef);

  return (
    <span className="text-xs text-muted-foreground">
      {showPrefix && __('Source: ', 'wp-sms')}{label}
      {detail && <span className="opacity-70"> ({detail})</span>}
    </span>
  );
}
