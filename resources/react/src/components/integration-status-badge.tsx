import { Badge } from '@/components/ui/badge';

interface IntegrationStatusBadgeProps {
  connected: boolean;
  available: boolean;
}

export function IntegrationStatusBadge({ connected, available }: IntegrationStatusBadgeProps) {
  if (connected) {
    return <Badge variant="success" dot className="text-xs">Connected</Badge>;
  }
  if (!available) {
    return <Badge variant="outline" className="text-xs text-muted-foreground">Not Available</Badge>;
  }
  return <Badge variant="outline" className="text-xs">Available</Badge>;
}
