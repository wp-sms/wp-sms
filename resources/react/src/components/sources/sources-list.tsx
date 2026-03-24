import { formatDate } from '@/lib/format';
import { useState, useCallback } from 'react';
import { Users, RefreshCw, Settings, Unplug } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { SourceConfigSheet } from './source-config-sheet';
import { SyncProgress } from './sync-progress';
import { useContactSources } from '@/hooks/use-contact-sources';
import type { ContactSource, ContactSourceConfig } from '@/lib/api';

const SOURCE_ICONS: Record<string, React.ReactNode> = {
  wordpress: <Users className="size-5" />,
};

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  connected: 'default',
  disconnected: 'secondary',
  syncing: 'outline',
  error: 'destructive',
};

export function SourcesList() {
  const { sources, loading, refetch, connect, update, disconnect, startSync, getStatus, getFields } = useContactSources();
  const [configSource, setConfigSource] = useState<ContactSource | null>(null);
  const [syncingSource, setSyncingSource] = useState<{ type: string; total: number } | null>(null);

  const handleSave = async (type: string, config: ContactSourceConfig) => {
    const source = sources.find((s) => s.type === type);
    if (source?.status === 'disconnected') {
      await connect(type, config);
    } else {
      await update(type, config);
    }
  };

  const handleSync = async (type: string) => {
    const result = await startSync(type);
    setSyncingSource({ type, total: result.total_available });
  };

  const handleSyncComplete = useCallback(() => {
    setSyncingSource(null);
    refetch();
  }, [refetch]);

  if (loading) {
    return <p className="text-sm text-muted-foreground py-8 text-center">Loading sources...</p>;
  }

  if (sources.length === 0) {
    return <EmptyState compact title="No contact sources available." />;
  }

  return (
    <>
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {sources.map((source) => (
          <Card key={source.type}>
            <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
              <div className="flex items-center gap-3">
                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                  {SOURCE_ICONS[source.icon] ?? <Users className="size-5" />}
                </div>
                <div>
                  <CardTitle className="text-base">{source.name}</CardTitle>
                  <Badge variant={STATUS_VARIANTS[source.status] ?? 'secondary'} className="mt-1">
                    {source.status}
                  </Badge>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <CardDescription className="text-sm">
                {source.description}
              </CardDescription>

              {source.status !== 'disconnected' && (
                <div className="grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <p className="text-muted-foreground">Contacts</p>
                    <p className="font-medium">{source.contact_count.toLocaleString()}</p>
                  </div>
                  {source.stats?.last_synced_at && (
                    <div>
                      <p className="text-muted-foreground">Last Synced</p>
                      <p className="font-medium">
                        {formatDate(source.stats.last_synced_at)}
                      </p>
                    </div>
                  )}
                </div>
              )}

              {syncingSource?.type === source.type && (
                <SyncProgress
                  sourceType={source.type}
                  totalAvailable={syncingSource.total}
                  getStatus={getStatus}
                  onComplete={handleSyncComplete}
                />
              )}

              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setConfigSource(source)}
                >
                  <Settings className="mr-1.5 size-3.5" />
                  {source.status === 'disconnected' ? 'Connect' : 'Configure'}
                </Button>

                {source.status === 'connected' && (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleSync(source.type)}
                  >
                    <RefreshCw className="mr-1.5 size-3.5" />
                    Sync Now
                  </Button>
                )}

                {source.status !== 'disconnected' && source.status !== 'syncing' && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => disconnect(source.type)}
                  >
                    <Unplug className="mr-1.5 size-3.5" />
                    Disconnect
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <SourceConfigSheet
        source={configSource}
        open={configSource !== null}
        onOpenChange={(open) => { if (!open) setConfigSource(null); }}
        onSave={handleSave}
        getFields={getFields}
      />
    </>
  );
}
