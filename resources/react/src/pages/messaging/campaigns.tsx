import { formatDateTime } from '@/lib/format';
import { useState } from 'react';
import { useCampaigns } from '@/hooks/use-campaigns';
import { CampaignEditor } from './campaign-editor';
import { CampaignDetail } from './campaign-detail';
import type { Campaign } from '@/lib/api';
import { CampaignStatusBadge } from '@/components/campaigns/campaign-status-badge';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageHeader } from '@/components/layout/page-header';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { CHANNEL_LABELS } from '@/components/gateway-config-form';
import { Plus, Megaphone, Pencil, Trash2, Copy, Eye, MoreHorizontal } from 'lucide-react';
import { pluralize } from '@/lib/utils';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

type View =
  | { mode: 'list' }
  | { mode: 'create' }
  | { mode: 'edit'; campaign: Campaign }
  | { mode: 'detail'; campaign: Campaign };

function ChannelBadge({ channel }: { channel: string }) {
  return (
    <Badge variant="outline">
      {CHANNEL_LABELS[channel] ?? channel}
    </Badge>
  );
}

export function Campaigns() {
  const {
    campaigns, total, page, perPage, filters, setFilter, setPage,
    loading, createCampaign, updateCampaign, deleteCampaign, duplicateCampaign, refetch,
  } = useCampaigns();
  const [view, setView] = useState<View>({ mode: 'list' });
  const [deleting, setDeleting] = useState<string | null>(null);
  const [duplicating, setDuplicating] = useState<string | null>(null);
  const confirm = useConfirm();

  const totalPages = Math.ceil(total / perPage);

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: 'Delete campaign?',
      description: 'This campaign and its delivery history will be permanently removed.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
    setDeleting(id);
    try {
      await deleteCampaign(id);
      toast.success('Campaign deleted.');
    } catch {
      toast.error('Failed to delete campaign.');
    } finally {
      setDeleting(null);
    }
  };

  const handleDuplicate = async (id: string) => {
    setDuplicating(id);
    try {
      const clone = await duplicateCampaign(id);
      toast.success('Campaign duplicated.');
      setView({ mode: 'edit', campaign: clone });
    } catch {
      toast.error('Failed to duplicate campaign.');
    } finally {
      setDuplicating(null);
    }
  };

  if (view.mode === 'create') {
    return (
      <CampaignEditor
        onSave={(data, id) => id ? updateCampaign(id, data) : createCampaign(data)}
        onBack={() => { setView({ mode: 'list' }); refetch(); }}
      />
    );
  }

  if (view.mode === 'edit') {
    return (
      <CampaignEditor
        campaign={view.campaign}
        onSave={(data, id) => updateCampaign(id ?? view.campaign.id, data)}
        onBack={() => { setView({ mode: 'list' }); refetch(); }}
      />
    );
  }

  if (view.mode === 'detail') {
    return (
      <CampaignDetail
        campaign={view.campaign}
        onBack={() => { setView({ mode: 'list' }); refetch(); }}
      />
    );
  }

  return (
    <div className="space-y-4">
      <PageHeader
        icon={Megaphone}
        title="Campaigns"
        metadata={pluralize(total, 'campaign')}
        actions={
          <Button size="sm" onClick={() => setView({ mode: 'create' })}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            New Campaign
          </Button>
        }
      />
      <div className="flex items-center gap-3">
        <Select
          value={filters.status || 'all'}
          onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}
        >
          <SelectTrigger className="w-40">
            <SelectValue placeholder="All Statuses" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Statuses</SelectItem>
            <SelectItem value="draft">Draft</SelectItem>
            <SelectItem value="scheduled">Scheduled</SelectItem>
            <SelectItem value="sending">Sending</SelectItem>
            <SelectItem value="paused">Paused</SelectItem>
            <SelectItem value="sent">Sent</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
            <SelectItem value="failed">Failed</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={filters.channel || 'all'}
          onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}
        >
          <SelectTrigger className="w-40">
            <SelectValue placeholder="All Channels" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Channels</SelectItem>
            {Object.entries(CHANNEL_LABELS).map(([value, label]) => (
              <SelectItem key={value} value={value}>{label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {(filters.status || filters.channel) && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { setFilter('status', ''); setFilter('channel', ''); }}
          >
            Clear filters
          </Button>
        )}
      </div>

      <DataTable
        loading={loading}
        isEmpty={campaigns.length === 0}
        empty={
          <EmptyState
            icon={Megaphone}
            title="No campaigns found"
            description="Create your first campaign to start broadcasting messages."
            action={
              <Button size="sm" onClick={() => setView({ mode: 'create' })}>
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                New Campaign
              </Button>
            }
          />
        }
        pagination={{ page, totalPages, onPageChange: setPage }}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Channel</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Recipients</TableHead>
              <TableHead className="text-right">Sent</TableHead>
              <TableHead>Scheduled</TableHead>
              <TableHead className="w-[70px]" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {campaigns.map((campaign) => {
              const canEdit = campaign.status === 'draft' || campaign.status === 'scheduled';
              const canDelete = campaign.status !== 'sending';
              return (
                <TableRow key={campaign.id} className="even:bg-muted/30">
                  <TableCell className="font-medium">
                    <button
                      className="text-left text-primary/80 transition-colors hover:text-primary"
                      onClick={() => setView({ mode: 'detail', campaign })}
                    >
                      {campaign.name}
                    </button>
                  </TableCell>
                  <TableCell><ChannelBadge channel={campaign.channel} /></TableCell>
                  <TableCell><CampaignStatusBadge status={campaign.status} /></TableCell>
                  <TableCell className="text-right text-sm tabular-nums">
                    {campaign.total_recipients > 0 ? campaign.total_recipients.toLocaleString() : '\u2014'}
                  </TableCell>
                  <TableCell className="text-right text-sm tabular-nums">
                    {campaign.sent_count > 0 ? campaign.sent_count.toLocaleString() : '\u2014'}
                  </TableCell>
                  <TableCell className="text-sm">
                    {campaign.send_at ? formatDateTime(campaign.send_at) : '\u2014'}
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setView({ mode: 'detail', campaign })}>
                          <Eye className="h-4 w-4 mr-2" />
                          View
                        </DropdownMenuItem>
                        {canEdit && (
                          <DropdownMenuItem onClick={() => setView({ mode: 'edit', campaign })}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                          onClick={() => void handleDuplicate(campaign.id)}
                          disabled={duplicating === campaign.id}
                        >
                          <Copy className="h-4 w-4 mr-2" />
                          Duplicate
                        </DropdownMenuItem>
                        {canDelete && (
                          <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => void handleDelete(campaign.id)}
                              disabled={deleting === campaign.id}
                              className="text-destructive focus:text-destructive"
                            >
                              <Trash2 className="h-4 w-4 mr-2" />
                              Delete
                            </DropdownMenuItem>
                          </>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}
