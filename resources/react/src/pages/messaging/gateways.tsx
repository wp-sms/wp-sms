import { __, _n, sprintf } from '@wordpress/i18n';
import { useState, useMemo, useEffect } from 'react';
import { useGateways } from '@/hooks/use-gateways';
import { GatewayConfigPanel } from '@/components/gateway-config-panel';
import { channelLabel, ensureConfig } from '@/components/gateway-config-form';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription } from '@/components/ui/drawer';
import { Search, Star, ChevronRight, Radio, Smartphone, Mail, MessageSquare, MessageCircle, MessageCircleMore, MessagesSquare, Send, Image, Phone, Check } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { getGatewayColor, getGatewayInitial } from '@/lib/gateway-visuals';
import type { Gateway } from '@/lib/api';

const FEATURE_LABELS: Record<string, string> = {
  mms: __('MMS', 'wp-sms'),
  delivery_receipt: __('Receipts', 'wp-sms'),
  incoming: __('Incoming', 'wp-sms'),
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
      {__('Balance:', 'wp-sms')} <span className="font-medium text-foreground">{credit}</span>
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

  return (
    <Card active={gateway.is_configured} onClick={onConfigure} className="cursor-pointer">
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-white text-sm font-semibold" style={{ backgroundColor: getGatewayColor(gateway) }}>
              {getGatewayInitial(gateway.name)}
            </div>
            <CardTitle className="text-base">{gateway.name}</CardTitle>
          </div>
          <div className="flex items-center gap-2">
            {gateway.is_configured
              ? <Badge variant="success" dot className="text-xs">{__('Active', 'wp-sms')}</Badge>
              : <Badge variant="outline" className="text-xs text-muted-foreground">{__('Not configured', 'wp-sms')}</Badge>
            }
            <ChevronRight className="h-4 w-4 text-muted-foreground rtl:scale-x-[-1]" />
          </div>
        </div>
        {gateway.metadata.description && (
          <CardDescription className="line-clamp-2">{gateway.metadata.description}</CardDescription>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="flex flex-wrap gap-1.5">
          {gateway.supported_channels.map((ch) => {
            const isConfigured = gateway.configured_channels.includes(ch);
            const isDefault = isConfigured && Boolean(gateway.config?.is_default?.[ch]);
            return (
              <Badge
                key={ch}
                variant={isDefault ? "default" : "outline"}
                className={!isConfigured ? "opacity-50" : undefined}
              >
                {isDefault && <Star className="h-3 w-3" />}
                {channelLabel(ch)}
              </Badge>
            );
          })}
        </div>

        {features.length > 0 && (
          <p className="text-xs text-muted-foreground">
            {__('Supports:', 'wp-sms')} {features.join(', ')}
          </p>
        )}

        <CreditDisplay gatewayId={gateway.id} isConfigured={gateway.is_configured} getCredit={getCredit} />
      </CardContent>
    </Card>
  );
}

const CHANNEL_ICONS: Record<string, React.ReactNode> = {
  sms: <Smartphone className="h-4 w-4" />,
  email: <Mail className="h-4 w-4" />,
  whatsapp: <MessageSquare className="h-4 w-4" />,
  telegram: <Send className="h-4 w-4" />,
  line: <MessageCircle className="h-4 w-4" />,
  viber: <MessageCircleMore className="h-4 w-4" />,
  mms: <Image className="h-4 w-4" />,
  rcs: <MessagesSquare className="h-4 w-4" />,
  voice: <Phone className="h-4 w-4" />,
};

export function Gateways({ embedded }: { embedded?: boolean } = {}) {
  const { gateways, loading, updateConfig, testGateway, testConnection, getCredit } = useGateways();
  const [selectedGatewayId, setSelectedGatewayId] = useState<string | null>(null);
  const [defaultsOpen, setDefaultsOpen] = useState(false);
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
    return gateways
      .filter((g) => {
        if (searchQuery) {
          const q = searchQuery.toLowerCase();
          if (!g.name.toLowerCase().includes(q) && !g.id.toLowerCase().includes(q)) return false;
        }
        if (channelFilter !== 'all' && !g.supported_channels.includes(channelFilter)) return false;
        return true;
      })
      .sort((a, b) => {
        const aDefault = Object.values(a.config?.is_default ?? {}).some(Boolean);
        const bDefault = Object.values(b.config?.is_default ?? {}).some(Boolean);
        if (aDefault !== bDefault) return aDefault ? -1 : 1;
        if (a.is_configured !== b.is_configured) return a.is_configured ? -1 : 1;
        return 0;
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

  // Auto-assign defaults for channels with a single configured gateway
  useEffect(() => {
    const configMap: Record<string, Record<string, unknown>> = {};

    for (const ch of allChannels) {
      if (channelDefaults[ch]) continue;
      const supporting = gateways.filter(g => g.configured_channels.includes(ch));
      if (supporting.length !== 1) continue;

      const g = supporting[0];
      if (!configMap[g.id]) {
        const cfg = ensureConfig(g.config);
        configMap[g.id] = { ...cfg, is_default: { ...cfg.is_default } } as unknown as Record<string, unknown>;
      }
      (configMap[g.id] as unknown as { is_default: Record<string, boolean> }).is_default[ch] = true;
    }

    if (Object.keys(configMap).length > 0) {
      updateConfig(configMap);
    }
  }, [gateways, allChannels, channelDefaults, updateConfig]);

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
      {!embedded && (
        <PageHeader
          icon={Radio}
          title={__('Gateways', 'wp-sms')}
          metadata={sprintf(_n('%d gateway', '%d gateways', gateways.length, 'wp-sms'), gateways.length)}
          actions={
            allChannels.length > 0 ? (
              <Button variant="outline" size="sm" onClick={() => setDefaultsOpen(true)}>
                {__('Channel Defaults', 'wp-sms')}
              </Button>
            ) : undefined
          }
        />
      )}

      {/* Filter Bar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder={__('Search gateways...', 'wp-sms')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="ps-9"
          />
        </div>
        {allChannels.length > 1 && (
          <Select value={channelFilter} onValueChange={setChannelFilter}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All Channels', 'wp-sms')}</SelectItem>
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
          <p className="text-sm text-muted-foreground">{__('No gateways match your filters', 'wp-sms')}</p>
        </div>
      )}

      {/* Config Panel */}
      <GatewayConfigPanel
        open={!!selectedGatewayId}
        onOpenChange={(open) => { if (!open) setSelectedGatewayId(null); }}
        gateway={selectedGateway}
        onSave={updateConfig}
        onTest={testGateway}
        onTestConnection={testConnection}
      />

      {/* Channel Defaults Drawer */}
      <Drawer open={defaultsOpen} onOpenChange={setDefaultsOpen}>
        <DrawerContent className="sm:max-w-sm">
          <DrawerHeader>
            <DrawerTitle>{__('Channel Defaults', 'wp-sms')}</DrawerTitle>
            <DrawerDescription>{__('Select the default gateway for each messaging channel', 'wp-sms')}</DrawerDescription>
          </DrawerHeader>
          <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-4">
            {allChannels.filter(ch => ch !== 'webhook').map((ch) => {
              const supportingGateways = gateways.filter((g) => g.configured_channels.includes(ch));
              return (
                <div key={ch} className="flex items-center gap-3">
                  <div className="flex items-center gap-2 w-28 shrink-0">
                    <span className="text-muted-foreground">{CHANNEL_ICONS[ch]}</span>
                    <span className="text-sm font-medium">{channelLabel(ch)}</span>
                  </div>

                  {supportingGateways.length === 0 ? (
                    <span className="text-sm text-muted-foreground">{__('No configured gateway', 'wp-sms')}</span>
                  ) : supportingGateways.length === 1 ? (
                    <div className="flex items-center gap-2 text-sm">
                      <span>{supportingGateways[0].name}</span>
                      <Check className="h-3.5 w-3.5 text-muted-foreground" />
                    </div>
                  ) : (
                    <Select
                      value={channelDefaults[ch] ?? ''}
                      onValueChange={(v) => handleChangeDefault(ch, v)}
                    >
                      <SelectTrigger className="flex-1">
                        <SelectValue placeholder={__('Select default', 'wp-sms')} />
                      </SelectTrigger>
                      <SelectContent>
                        {supportingGateways.map((g) => (
                          <SelectItem key={g.id} value={g.id}>
                            {g.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </div>
              );
            })}
          </div>
        </DrawerContent>
      </Drawer>
    </div>
  );
}
