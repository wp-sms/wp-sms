import { useState, type ComponentType } from 'react';
import { useIntegrationAvailability } from '@/hooks/use-integrations';
import { IntegrationIcon } from '@/components/integration-icon';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ArrowLeft } from 'lucide-react';
import { WooCommerce } from './woocommerce';
import { CF7Verification } from './cf7-verification';
import { getConfig } from '@/lib/api';
import type { AuthSettings } from '@/lib/api';

interface IntegrationsPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

type SettingsProps = { settings: Required<AuthSettings>; onUpdate: IntegrationsPageProps['onUpdate'] };

interface AuthIntegrationDef {
  id: string;
  name: string;
  description: string;
  icon: string;
  component: ComponentType<SettingsProps>;
}

const AUTH_INTEGRATIONS: AuthIntegrationDef[] = [
  {
    id: 'woocommerce',
    name: 'WooCommerce',
    description: 'Checkout verification and authentication redirects for WooCommerce pages.',
    icon: 'shopping-cart',
    component: WooCommerce,
  },
  {
    id: 'contactform7',
    name: 'Contact Form 7',
    description: 'Email and phone OTP verification fields for Contact Form 7 forms.',
    icon: 'file-text',
    component: CF7Verification,
  },
];

function StatusBadge({ available }: { available?: boolean }) {
  if (available === undefined) return null;
  if (available) {
    return <Badge variant="default" className="text-xs">Detected</Badge>;
  }
  return <Badge variant="outline" className="text-xs text-muted-foreground">Not Installed</Badge>;
}

const AUTH_INTEGRATION_IDS = AUTH_INTEGRATIONS.map((i) => i.id) as readonly string[];

export function IntegrationsPage({ section: _section, settings, onUpdate }: IntegrationsPageProps) {
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const { availabilityMap, loading: loadingIntegrations } = useIntegrationAvailability(AUTH_INTEGRATION_IDS);

  const selected = AUTH_INTEGRATIONS.find((i) => i.id === selectedId);

  if (selected) {
    const isAvailable = availabilityMap.get(selected.id);

    return (
      <div className="space-y-6">
        <Button variant="ghost" size="sm" className="-ml-2" onClick={() => setSelectedId(null)}>
          <ArrowLeft className="mr-1 h-4 w-4" />
          Back to Integrations
        </Button>
        <div className="flex items-center gap-3">
          <IntegrationIcon icon={selected.icon} size="lg" />
          <div>
            <h2 className="text-base font-semibold">{selected.name}</h2>
            <p className="text-sm text-muted-foreground">{selected.description}</p>
          </div>
        </div>
        {isAvailable === false && (
          <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
            {selected.name} is not installed. Settings can be pre-configured but won&apos;t take effect until the plugin is active.
          </p>
        )}
        <selected.component settings={settings} onUpdate={onUpdate} />
        <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
          Looking for messaging and automation settings?{' '}
          <a href={`${getConfig().adminUrl}admin.php?page=wsms-messaging#apps`} className="text-primary hover:underline">
            Messaging &rarr; Apps
          </a>
        </p>
      </div>
    );
  }

  if (loadingIntegrations) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {Array.from({ length: 2 }).map((_, i) => (
          <Skeleton key={i} className="h-44" />
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {AUTH_INTEGRATIONS.map((integration) => (
        <Card key={integration.id} className="flex flex-col">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <IntegrationIcon icon={integration.icon} size="md" />
                <CardTitle className="text-base">{integration.name}</CardTitle>
              </div>
              <StatusBadge available={availabilityMap.get(integration.id)} />
            </div>
            <CardDescription className="line-clamp-2">{integration.description}</CardDescription>
          </CardHeader>
          <CardContent className="mt-auto">
            <Button variant="outline" size="sm" className="w-full" onClick={() => setSelectedId(integration.id)}>
              Settings
            </Button>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
