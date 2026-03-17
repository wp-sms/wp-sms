import { useState, useMemo, useEffect } from 'react';
import { useGateways } from '@/hooks/use-gateways';
import { GatewayConfigSheet } from '@/components/gateway-config-sheet';
import { channelLabel, ensureConfig } from '@/components/gateway-config-form';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
import { Search, Settings, Star, ChevronDown } from 'lucide-react';
import type { Gateway } from '@/lib/api';

const FEATURE_LABELS: Record<string, string> = {
  mms: 'MMS',
  delivery_receipt: 'Receipts',
  incoming: 'Incoming',
};

function CreditDisplay({ gatewayId, isConfigured, getCredit }: {
  gatewayId: string;
  isConfigured: boolean;
  getCredit: (id: string) => Promise<string | null>;
}) {
  const [credit, setCredit] = useState<string | null | undefined>(undefined);

  useEffect(() => {
    if (!isConfigured) return;
    let cancelled = false;
    getCredit(gatewayId).then((c) => { if (!cancelled) setCredit(c); });
    return () => { cancelled = true; };
  }, [gatewayId, isConfigured, getCredit]);

  if (!isConfigured || credit === undefined || credit === null) return null;

  return (
    <div className="text-xs text-muted-foreground">
      Balance: <span className="font-medium text-foreground">{credit}</span>
    </div>
  );
}

function GatewayCard({ gateway, getCredit, onConfigure }: {
  gateway: Gateway;
  getCredit: (id: string) => Promise<string | null>;
  onConfigure: () => void;
}) {
  const features = Object.entries(gateway.features)
    .filter(([key, val]) => val && key !== 'unicode' && FEATURE_LABELS[key])
    .map(([key]) => FEATURE_LABELS[key]);

  const defaultChannels = gateway.supported_channels.filter(
    (ch) => gateway.config?.is_default?.[ch]
  );

  return (
    <Card className={gateway.is_configured ? 'border-l-2 border-l-primary' : ''}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">{gateway.name}</CardTitle>
          {gateway.is_configured
            ? <Badge variant="default" className="text-xs">Active</Badge>
            : <Badge variant="outline" className="text-xs text-muted-foreground">Not configured</Badge>
          }
        </div>
        {gateway.metadata.description && (
          <CardDescription className="line-clamp-2">{gateway.metadata.description}</CardDescription>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="flex flex-wrap gap-1.5">
          {gateway.supported_channels.map((ch) => (
            <Badge key={ch} variant="outline">{channelLabel(ch)}</Badge>
          ))}
          {defaultChannels.map((ch) => (
            <Badge key={`default-${ch}`} variant="secondary" className="text-xs">
              <Star className="h-3 w-3" />
              Default for {channelLabel(ch)}
            </Badge>
          ))}
        </div>

        {gateway.metadata.regions && gateway.metadata.regions.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {gateway.metadata.regions.map((r) => (
              <Badge key={r} variant="secondary" className="text-xs">{r}</Badge>
            ))}
          </div>
        )}

        {features.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {features.map((f) => (
              <Badge key={f} variant="ghost" className="text-xs text-muted-foreground">{f}</Badge>
            ))}
          </div>
        )}

        <CreditDisplay gatewayId={gateway.id} isConfigured={gateway.is_configured} getCredit={getCredit} />

        <Button variant="outline" size="sm" onClick={onConfigure} className="w-full">
          <Settings className="h-4 w-4" />
          Configure
        </Button>
      </CardContent>
    </Card>
  );
}

export function Gateways() {
  const { gateways, loading, updateConfig, testGateway, getCredit } = useGateways();
  const [selectedGatewayId, setSelectedGatewayId] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [channelFilter, setChannelFilter] = useState('all');

  // All unique channels across gateways
  const allChannels = useMemo(() => {
    const set = new Set<string>();
    gateways.forEach((g) => g.supported_channels.forEach((ch) => set.add(ch)));
    return Array.from(set).sort();
  }, [gateways]);

  // Filtered gateways
  const filtered = useMemo(() => {
    return gateways.filter((g) => {
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        if (!g.name.toLowerCase().includes(q) && !g.id.toLowerCase().includes(q)) return false;
      }
      if (channelFilter !== 'all' && !g.supported_channels.includes(channelFilter)) return false;
      return true;
    });
  }, [gateways, searchQuery, channelFilter]);

  // Channel defaults: for each channel, which gateway is default
  const channelDefaults = useMemo(() => {
    const map: Record<string, string> = {};
    for (const ch of allChannels) {
      for (const g of gateways) {
        if (g.supported_channels.includes(ch) && g.config?.is_default?.[ch]) {
          map[ch] = g.id;
          break;
        }
      }
    }
    return map;
  }, [gateways, allChannels]);

  const selectedGateway = useMemo(
    () => gateways.find((g) => g.id === selectedGatewayId) ?? null,
    [gateways, selectedGatewayId],
  );

  async function handleChangeDefault(channel: string, gatewayId: string) {
    const configMap: Record<string, Record<string, unknown>> = {};
    const oldDefaultId = channelDefaults[channel];
    for (const g of gateways) {
      if (g.id !== gatewayId && g.id !== oldDefaultId) continue;
      const cfg = ensureConfig(g.config);
      configMap[g.id] = { ...cfg, is_default: { ...cfg.is_default, [channel]: g.id === gatewayId } } as unknown as Record<string, unknown>;
    }
    await updateConfig(configMap);
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-24 w-full" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-48" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Channel Defaults */}
      {allChannels.length > 0 && (
        <Collapsible>
          <Card>
            <CollapsibleTrigger asChild>
              <CardHeader className="group cursor-pointer">
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>Channel Defaults</CardTitle>
                    <CardDescription>Select the default gateway for each messaging channel</CardDescription>
                  </div>
                  <ChevronDown className="h-4 w-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                </div>
                <div className="text-xs text-muted-foreground group-data-[state=open]:hidden">
                  {allChannels
                    .filter((ch) => channelDefaults[ch])
                    .map((ch) => `${channelLabel(ch)}: ${gateways.find((g) => g.id === channelDefaults[ch])?.name ?? 'Unknown'}`)
                    .join(', ') || 'No defaults set'}
                </div>
              </CardHeader>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <CardContent className="space-y-4">
                {allChannels.map((ch) => {
                  const supportingGateways = gateways.filter((g) => g.supported_channels.includes(ch) && g.is_configured);
                  return (
                    <div key={ch} className="flex items-center justify-between gap-4">
                      <span className="text-sm font-medium w-24">{channelLabel(ch)}</span>
                      <Select
                        value={channelDefaults[ch] ?? ''}
                        onValueChange={(v) => handleChangeDefault(ch, v)}
                      >
                        <SelectTrigger className="w-64">
                          <SelectValue placeholder="No default set" />
                        </SelectTrigger>
                        <SelectContent>
                          {supportingGateways.map((g) => (
                            <SelectItem key={g.id} value={g.id}>
                              {g.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  );
                })}
              </CardContent>
            </CollapsibleContent>
          </Card>
        </Collapsible>
      )}

      {/* Filter Bar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder="Search gateways..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>
        {allChannels.length > 1 && (
          <Select value={channelFilter} onValueChange={setChannelFilter}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Channels</SelectItem>
              {allChannels.map((ch) => (
                <SelectItem key={ch} value={ch}>{channelLabel(ch)}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </div>

      {/* Gateway Cards */}
      {filtered.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filtered.map((g) => (
            <GatewayCard
              key={g.id}
              gateway={g}
              getCredit={getCredit}
              onConfigure={() => setSelectedGatewayId(g.id)}
            />
          ))}
        </div>
      ) : (
        <div className="flex items-center justify-center py-12">
          <p className="text-sm text-muted-foreground">No gateways match your filters</p>
        </div>
      )}

      {/* Config Sheet */}
      <GatewayConfigSheet
        open={!!selectedGatewayId}
        onOpenChange={(open) => { if (!open) setSelectedGatewayId(null); }}
        gateway={selectedGateway}
        onSave={updateConfig}
        onTest={testGateway}
      />
    </div>
  );
}
