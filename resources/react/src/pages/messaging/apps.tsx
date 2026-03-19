import { useState, useMemo } from 'react';
import { useIntegrations } from '@/hooks/use-integrations';
import { IntegrationDetailDrawer } from '@/components/integration-detail-drawer';
import { IntegrationIcon } from '@/components/integration-icon';
import { IntegrationStatusBadge } from '@/components/integration-status-badge';
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
import { Search } from 'lucide-react';
import { INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';
import type { PlatformIntegration } from '@/lib/api';

function IntegrationCard({ integration, onClick }: {
  integration: PlatformIntegration;
  onClick: () => void;
}) {
  return (
    <Card className={integration.connected ? 'border-l-2 border-l-primary' : ''}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <IntegrationIcon icon={integration.icon} size="md" />
            <CardTitle className="text-base">{integration.name}</CardTitle>
          </div>
          <IntegrationStatusBadge connected={integration.connected} available={integration.available} />
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
          {integration.triggers} Trigger{integration.triggers !== 1 ? 's' : ''}
          {' · '}
          {integration.actions} Action{integration.actions !== 1 ? 's' : ''}
        </div>

        <Button variant="outline" size="sm" onClick={onClick} className="w-full">
          {integration.auth_type !== 'none' && !integration.connected ? 'Configure' : 'View Details'}
        </Button>
      </CardContent>
    </Card>
  );
}

export function Apps() {
  const { integrations, loading, refetch } = useIntegrations();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');

  const allCategories = useMemo(() => {
    const set = new Set<string>();
    integrations.forEach((i) => set.add(i.category));
    return Array.from(set).sort();
  }, [integrations]);

  const filtered = useMemo(() => {
    return integrations.filter((i) => {
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        if (!i.name.toLowerCase().includes(q) && !i.id.toLowerCase().includes(q)) return false;
      }
      if (categoryFilter !== 'all' && i.category !== categoryFilter) return false;
      return true;
    });
  }, [integrations, searchQuery, categoryFilter]);

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

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder="Search apps..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>
        {allCategories.length > 1 && (
          <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Categories</SelectItem>
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
          <p className="text-sm text-muted-foreground">No apps match your filters</p>
        </div>
      )}

      <IntegrationDetailDrawer
        open={!!selectedId}
        onOpenChange={(open) => { if (!open) setSelectedId(null); }}
        integrationId={selectedId}
        onConfigChange={refetch}
      />
    </div>
  );
}
