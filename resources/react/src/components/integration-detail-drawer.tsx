import { useState, useEffect } from 'react';
import { useIntegrationDetail, useIntegrationConfig } from '@/hooks/use-integrations';
import { IntegrationIcon } from '@/components/integration-icon';
import { IntegrationStatusBadge } from '@/components/integration-status-badge';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Loader2, Zap, Play, type LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';

interface IntegrationDetailDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  integrationId: string | null;
  onConfigChange: () => void;
}

function CapabilityList({ title, icon: Icon, iconClass, items }: {
  title: string;
  icon: LucideIcon;
  iconClass: string;
  items: Array<{ id: string; name: string; description: string }>;
}) {
  if (items.length === 0) return null;
  return (
    <div className="space-y-3">
      <h3 className="text-sm font-medium flex items-center gap-2">
        <Icon className={`h-4 w-4 ${iconClass}`} />
        {title} ({items.length})
      </h3>
      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border p-3">
            <div className="text-sm font-medium">{item.name}</div>
            <div className="text-xs text-muted-foreground mt-0.5">{item.description}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function IntegrationDetailDrawer({
  open,
  onOpenChange,
  integrationId,
  onConfigChange,
}: IntegrationDetailDrawerProps) {
  const { detail, loading } = useIntegrationDetail(open ? integrationId : null);
  const [disconnecting, setDisconnecting] = useState(false);
  const { disconnect } = useIntegrationConfig(onConfigChange);

  useEffect(() => {
    if (open) setDisconnecting(false);
  }, [open]);

  async function handleDisconnect() {
    if (!detail) return;
    setDisconnecting(true);
    try {
      await disconnect(detail.id);
      toast.success(`${detail.name} disconnected`);
      onOpenChange(false);
    } catch {
      toast.error('Failed to disconnect');
    } finally {
      setDisconnecting(false);
    }
  }

  return (
    <Drawer open={open} onOpenChange={onOpenChange} direction="right">
      <DrawerContent className="sm:max-w-md">
        {loading || !detail ? (
          <div className="space-y-4 p-6">
            <Skeleton className="h-10 w-10 rounded-lg" />
            <Skeleton className="h-6 w-48" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-32 w-full" />
          </div>
        ) : (
          <>
            <DrawerHeader className="shrink-0">
              <div className="flex gap-3">
                <IntegrationIcon icon={detail.icon} size="lg" className="mt-[3px]" />
                <div>
                  <DrawerTitle>{detail.name}</DrawerTitle>
                  <DrawerDescription className="mt-1">{detail.description}</DrawerDescription>
                </div>
              </div>
              <div className="flex items-center gap-2 mt-2">
                <IntegrationStatusBadge connected={detail.connected} available={detail.available} />
                <Badge variant="outline" className="text-xs">
                  {INTEGRATION_CATEGORY_LABELS[detail.category] ?? detail.category}
                </Badge>
              </div>
            </DrawerHeader>

            <div className="flex-1 overflow-y-auto space-y-6 px-4 pb-4">
              {detail.auth_type !== 'none' && detail.connected && (
                <>
                  <div className="space-y-3">
                    <h3 className="text-sm font-medium">Connection</h3>
                    <p className="text-xs text-muted-foreground">
                      This integration is connected and active.
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={handleDisconnect}
                      disabled={disconnecting}
                    >
                      {disconnecting && <Loader2 className="h-4 w-4 animate-spin" />}
                      Disconnect
                    </Button>
                  </div>
                  <Separator />
                </>
              )}

              <CapabilityList title="Triggers" icon={Zap} iconClass="text-amber-500" items={detail.triggers} />
              <CapabilityList title="Actions" icon={Play} iconClass="text-blue-500" items={detail.actions} />

              {detail.triggers.length === 0 && detail.actions.length === 0 && (
                <p className="text-sm text-muted-foreground">
                  This integration has no triggers or actions yet.
                </p>
              )}

              {(detail.triggers.length > 0 || detail.actions.length > 0) && (
                <p className="text-xs text-muted-foreground">
                  Use these triggers and actions in your Flows.
                </p>
              )}
            </div>

          </>
        )}
      </DrawerContent>
    </Drawer>
  );
}
