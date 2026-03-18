import type { CampaignStatus } from '@/lib/api';
import { Badge } from '@/components/ui/badge';

export const STATUS_STYLES: Record<CampaignStatus, { label: string; classes: string }> = {
  draft:     { label: 'Draft',     classes: '' },
  scheduled: { label: 'Scheduled', classes: 'border-blue-200 bg-blue-50 text-blue-700' },
  sending:   { label: 'Sending',   classes: 'border-amber-200 bg-amber-50 text-amber-700' },
  paused:    { label: 'Paused',    classes: 'border-orange-200 bg-orange-50 text-orange-700' },
  sent:      { label: 'Sent',      classes: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
  cancelled: { label: 'Cancelled', classes: 'border-gray-200 bg-gray-50 text-gray-600' },
  failed:    { label: 'Failed',    classes: 'border-red-200 bg-red-50 text-red-700' },
};

export function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
  const style = STATUS_STYLES[status] ?? STATUS_STYLES.draft;
  return (
    <Badge variant={status === 'draft' ? 'secondary' : 'outline'} className={style.classes}>
      {style.label}
    </Badge>
  );
}
