import { __ } from '@wordpress/i18n';
import type { CampaignStatus } from '@/lib/api';
import { Badge } from '@/components/ui/badge';

const STATUS_VARIANTS: Record<CampaignStatus, { label: string; variant: 'secondary' | 'info' | 'warning' | 'success' | 'neutral' | 'destructive' }> = {
  draft:     { label: __('Draft', 'wp-sms'),     variant: 'secondary' },
  scheduled: { label: __('Scheduled', 'wp-sms'), variant: 'info' },
  sending:   { label: __('Sending', 'wp-sms'),   variant: 'warning' },
  paused:    { label: __('Paused', 'wp-sms'),    variant: 'warning' },
  sent:      { label: __('Sent', 'wp-sms'),      variant: 'success' },
  cancelled: { label: __('Cancelled', 'wp-sms'), variant: 'neutral' },
  failed:    { label: __('Failed', 'wp-sms'),    variant: 'destructive' },
};

export function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
  const style = STATUS_VARIANTS[status] ?? STATUS_VARIANTS.draft;
  return (
    <Badge variant={style.variant}>
      {style.label}
    </Badge>
  );
}
