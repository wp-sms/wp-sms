import { __ } from '@wordpress/i18n';
import { Info } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

export function AuthDisabledNotice() {
  return (
    <Alert variant="info" className="mb-4">
      <Info className="h-4 w-4" />
      <AlertDescription>
        {__('Authentication is currently disabled. You can configure settings here — they\'ll take effect when you enable authentication in Channels.', 'wp-sms')}
      </AlertDescription>
    </Alert>
  );
}
