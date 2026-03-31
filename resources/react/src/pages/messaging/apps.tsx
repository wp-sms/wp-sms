import { __, sprintf, _n } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from 'react';
import { useIntegrations, useIntegrationDetail } from '@/hooks/use-integrations';
import { IntegrationIcon } from '@/components/integration-icon';
import { IntegrationStatusBadge } from '@/components/integration-status-badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, ArrowLeft, Blocks, ChevronRight, Info, RefreshCw, Search, Settings2, Zap, type LucideIcon } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';

import { IntegrationHero, IntegrationHeroSkeleton } from '@/components/integrations/integration-hero';
import { IntegrationOverviewTab } from '@/components/integrations/integration-overview-tab';
import { IntegrationDataTab } from '@/components/integrations/integration-data-tab';
import { IntegrationAutomationTab } from '@/components/integrations/integration-automation-tab';
import { WooCommerce } from '@/pages/integrations/woocommerce';
import { CF7Verification } from '@/pages/integrations/cf7-verification';
import type { AuthSettings, PlatformIntegration } from '@/lib/api';

interface AppsProps {
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

function IntegrationCard({ integration, onClick }: {
  integration: PlatformIntegration;
  onClick: () => void;
}) {
  return (
    <Card active={integration.connected} onClick={onClick} className="cursor-pointer">
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <IntegrationIcon icon={integration.icon} size="md" />
            <CardTitle className="text-base">{integration.name}</CardTitle>
          </div>
          <div className="flex items-center gap-2">
            <IntegrationStatusBadge connected={integration.connected} available={integration.available} />
            <ChevronRight className="h-4 w-4 text-muted-foreground rtl:scale-x-[-1]" />
          </div>
        </div>
        {integration.description && (
          <CardDescription className="line-clamp-2">{integration.description}</CardDescription>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <Badge variant="outline" className="text-xs">
          {INTEGRATION_CATEGORY_LABELS[integration.category] ?? integration.category}
        </Badge>

        <div className="text-xs text-muted-foreground">
          {sprintf(_n('%d Trigger', '%d Triggers', integration.triggers, 'wp-sms'), integration.triggers)}
          {' · '}
          {sprintf(_n('%d Action', '%d Actions', integration.actions, 'wp-sms'), integration.actions)}
        </div>

      </CardContent>
    </Card>
  );
}

interface TabDef {
  id: string;
  label: string;
  icon: LucideIcon;
}

function AppDetailPage({ integrationId, settings, onUpdate, onBack }: {
  integrationId: string;
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  onBack: () => void;
}) {
  const { detail, loading, refetch } = useIntegrationDetail(integrationId);
  const [activeTab, setActiveTab] = useState<string>('overview');

  const availableTabs = useMemo(() => {
    if (!detail) return [];
    const tabs: TabDef[] = [];

    const needsOverview = detail.auth_type !== 'none' || detail.connected;
    if (needsOverview)
      tabs.push({ id: 'overview', label: __('Overview', 'wp-sms'), icon: Info });

    const hasImport = detail.capabilities?.some((c) => c.id === 'contact_import' && c.supported);
    const hasSync = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);
    if ((hasImport || hasSync) && detail.connected)
      tabs.push({ id: 'data', label: __('Data Sync', 'wp-sms'), icon: RefreshCw });

    const hasCustomSettings = ['woocommerce', 'contactform7'].includes(detail.id);
    if (hasCustomSettings)
      tabs.push({ id: 'settings', label: __('Settings', 'wp-sms'), icon: Settings2 });

    if (detail.triggers.length > 0 || detail.actions.length > 0)
      tabs.push({ id: 'automation', label: __('Automation', 'wp-sms'), icon: Zap });

    return tabs;
  }, [detail]);

  // Tab fallback: reset to first available tab if current tab no longer exists
  useEffect(() => {
    if (availableTabs.length > 0 && !availableTabs.some((t) => t.id === activeTab)) {
      setActiveTab(availableTabs[0].id);
    }
  }, [availableTabs, activeTab]);

  const isMinimal = availableTabs.length <= 1;

  if (loading || !detail) {
    return (
      <div className="space-y-6">
        <Button variant="ghost" size="sm" className="-ms-2" onClick={onBack}>
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Back to Integrations', 'wp-sms')}
        </Button>
        <IntegrationHeroSkeleton />
        <Skeleton className="h-9 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" className="-ms-2" onClick={onBack}>
        <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
        {__('Back to Integrations', 'wp-sms')}
      </Button>

      <IntegrationHero detail={detail} onConfigChange={refetch} />

      {isMinimal ? (
        <div className="space-y-6">
          {availableTabs.some((t) => t.id === 'overview') && (
            <IntegrationOverviewTab detail={detail} onConfigChange={refetch} />
          )}
          {(detail.triggers.length > 0 || detail.actions.length > 0) && (
            <IntegrationAutomationTab triggers={detail.triggers} actions={detail.actions} />
          )}
        </div>
      ) : (
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList variant="line">
            {availableTabs.map((tab) => (
              <TabsTrigger key={tab.id} value={tab.id}>
                <tab.icon className="h-4 w-4" />
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>

          {availableTabs.some((t) => t.id === 'overview') && (
            <TabsContent value="overview">
              <IntegrationOverviewTab detail={detail} onConfigChange={refetch} />
            </TabsContent>
          )}

          {availableTabs.some((t) => t.id === 'data') && (
            <TabsContent value="data" forceMount className={activeTab !== 'data' ? 'hidden' : ''}>
              <IntegrationDataTab detail={detail} onRefresh={refetch} />
            </TabsContent>
          )}

          {availableTabs.some((t) => t.id === 'settings') && settings && onUpdate && (
            <TabsContent value="settings">
              {detail.id === 'woocommerce' && <WooCommerce settings={settings} onUpdate={onUpdate} />}
              {detail.id === 'contactform7' && <CF7Verification settings={settings} onUpdate={onUpdate} />}
            </TabsContent>
          )}

          {availableTabs.some((t) => t.id === 'automation') && (
            <TabsContent value="automation">
              <IntegrationAutomationTab triggers={detail.triggers} actions={detail.actions} />
            </TabsContent>
          )}
        </Tabs>
      )}
    </div>
  );
}

export function IntegrationsPage({ settings, onUpdate }: AppsProps) {
  const { integrations, loading, error } = useIntegrations();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');

  const allCategories = useMemo(() => {
    const set = new Set<string>();
    integrations.forEach((i) => set.add(i.category));
    return Array.from(set).sort();
  }, [integrations]);

  const filtered = useMemo(() => {
    const matched = integrations.filter((i) => {
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        if (!i.name.toLowerCase().includes(q) && !i.id.toLowerCase().includes(q)) return false;
      }
      if (categoryFilter !== 'all' && i.category !== categoryFilter) return false;
      return true;
    });
    return matched.sort((a, b) => {
      if (a.connected !== b.connected) return a.connected ? -1 : 1;
      return a.name.localeCompare(b.name);
    });
  }, [integrations, searchQuery, categoryFilter]);

  if (selectedId) {
    return (
      <AppDetailPage
        integrationId={selectedId}
        settings={settings}
        onUpdate={onUpdate}
        onBack={() => setSelectedId(null)}
      />
    );
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Skeleton className="h-9 w-64" />
          <Skeleton className="h-9 w-40" />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-48" />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <PageHeader icon={Blocks} title={__('Integrations', 'wp-sms')} />
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader icon={Blocks} title={__('Integrations', 'wp-sms')} metadata={sprintf(_n('%d integration', '%d integrations', integrations.length, 'wp-sms'), integrations.length)} />
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder={__('Search apps...', 'wp-sms')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="ps-9"
          />
        </div>
        {allCategories.length > 1 && (
          <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All Categories', 'wp-sms')}</SelectItem>
              {allCategories.map((cat) => (
                <SelectItem key={cat} value={cat}>
                  {INTEGRATION_CATEGORY_LABELS[cat] ?? cat}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </div>

      {filtered.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filtered.map((integration) => (
            <IntegrationCard
              key={integration.id}
              integration={integration}
              onClick={() => setSelectedId(integration.id)}
            />
          ))}
        </div>
      ) : (
        <div className="flex items-center justify-center py-12">
          <p className="text-sm text-muted-foreground">{__('No apps match your filters', 'wp-sms')}</p>
        </div>
      )}
    </div>
  );
}
