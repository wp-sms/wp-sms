import { __ } from '@wordpress/i18n';
import { Badge } from '@/components/ui/badge';
import { channelLabel } from '@/components/gateway-config-form';

const STATUS_LABELS: Record<string, string> = {
  delivered: __('Delivered', 'wp-sms'),
  sent: __('Sent', 'wp-sms'),
  queued: __('Queued', 'wp-sms'),
  failed: __('Failed', 'wp-sms'),
};

const STATUS_VARIANTS: Record<string, 'success' | 'info' | 'neutral' | 'destructive'> = {
  delivered: 'success',
  sent: 'info',
  queued: 'neutral',
  failed: 'destructive',
};

export function StatusBadge({ status }: { status: string }) {
  return (
    <Badge variant={STATUS_VARIANTS[status] ?? 'outline'}>
      {STATUS_LABELS[status] ?? status}
    </Badge>
  );
}

export function ChannelBadge({ channel, variant = 'secondary' }: { channel: string; variant?: 'secondary' | 'outline' }) {
  return <Badge variant={variant}>{channelLabel(channel)}</Badge>;
}
