import { Badge } from '@/components/ui/badge';
import { formatLabel } from '@/lib/constants';

export function StatusBadge({ status }: { status: string }) {
  switch (status) {
    case 'delivered':
      return <Badge variant="success">{status}</Badge>;
    case 'sent':
      return <Badge variant="info">{status}</Badge>;
    case 'queued':
      return <Badge variant="neutral">{status}</Badge>;
    case 'failed':
      return <Badge variant="destructive">{status}</Badge>;
    default:
      return <Badge variant="outline">{status}</Badge>;
  }
}

export function ChannelBadge({ channel }: { channel: string }) {
  return <Badge variant="secondary">{formatLabel(channel)}</Badge>;
}
