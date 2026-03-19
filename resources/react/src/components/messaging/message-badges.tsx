import { Badge } from '@/components/ui/badge';
import { formatLabel } from '@/lib/constants';

export function StatusBadge({ status }: { status: string }) {
  switch (status) {
    case 'delivered':
      return <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700">{status}</Badge>;
    case 'sent':
      return <Badge variant="outline" className="border-blue-200 bg-blue-50 text-blue-700">{status}</Badge>;
    case 'failed':
      return <Badge variant="destructive">{status}</Badge>;
    default:
      return <Badge variant="outline">{status}</Badge>;
  }
}

export function ChannelBadge({ channel }: { channel: string }) {
  return <Badge variant="secondary">{formatLabel(channel)}</Badge>;
}
