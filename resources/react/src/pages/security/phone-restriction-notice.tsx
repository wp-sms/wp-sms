import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Globe, ExternalLink } from 'lucide-react';
import { getConfig } from '@/lib/api';

export function PhoneRestrictionNotice() {
  const href = `${getConfig().adminUrl}admin.php?page=wsms-messaging#phone-restriction`;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Globe className="h-4 w-4 text-muted-foreground" />
          Phone Restrictions
        </CardTitle>
        <CardDescription>
          Country-based and number-type phone restrictions are configured in the Messaging settings area.
          This includes restrictions for both authentication and messaging.
        </CardDescription>
      </CardHeader>
      <CardContent className="border-t pt-4">
        <Button variant="outline" asChild>
          <a href={href}>
            Messaging &rarr; Settings &rarr; Phone Restrictions
            <ExternalLink className="ml-2 h-3.5 w-3.5" />
          </a>
        </Button>
      </CardContent>
    </Card>
  );
}
