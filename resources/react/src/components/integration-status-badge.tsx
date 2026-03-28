import { __ } from '@wordpress/i18n';
import { Badge } from '@/components/ui/badge';

interface IntegrationStatusBadgeProps {
  connected: boolean;
  available: boolean;
}

export function IntegrationStatusBadge({ connected, available }: IntegrationStatusBadgeProps) {
  if (connected) {
    return <Badge variant="success" dot className="text-xs">{__('Connected', 'wp-sms')}</Badge>;
  }
  if (!available) {
    return <Badge variant="outline" className="text-xs text-muted-foreground">{__('Not Available', 'wp-sms')}</Badge>;
  }
  return <Badge variant="outline" className="text-xs">{__('Available', 'wp-sms')}</Badge>;
}
