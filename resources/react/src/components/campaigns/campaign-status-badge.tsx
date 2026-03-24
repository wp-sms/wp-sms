import type { CampaignStatus } from '@/lib/api';
import { Badge } from '@/components/ui/badge';

const STATUS_VARIANTS: Record<CampaignStatus, { label: string; variant: 'secondary' | 'info' | 'warning' | 'success' | 'neutral' | 'destructive' }> = {
  draft:     { label: 'Draft',     variant: 'secondary' },
  scheduled: { label: 'Scheduled', variant: 'info' },
  sending:   { label: 'Sending',   variant: 'warning' },
  paused:    { label: 'Paused',    variant: 'warning' },
  sent:      { label: 'Sent',      variant: 'success' },
  cancelled: { label: 'Cancelled', variant: 'neutral' },
  failed:    { label: 'Failed',    variant: 'destructive' },
};

export function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
  const style = STATUS_VARIANTS[status] ?? STATUS_VARIANTS.draft;
  return (
    <Badge variant={style.variant}>
      {style.label}
    </Badge>
  );
}
