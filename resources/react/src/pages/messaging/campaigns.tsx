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
import { PageSection } from '@/components/ui/page-section';
import { Field, FieldLabel } from '@/components/ui/field';
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
import { CHANNEL_LABELS } from '@/components/gateway-config-form';
import { Plus, Megaphone, Pencil, Trash2, Copy, Eye } from 'lucide-react';
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
      <PageSection
        icon={Megaphone}
        title="Campaigns"
        description={<>{total} {total === 1 ? 'campaign' : 'campaigns'} total</>}
        actions={
          <Button size="sm" onClick={() => setView({ mode: 'create' })}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            New Campaign
          </Button>
        }
      >
          <div className="mb-4 flex items-center gap-3">
            <Field>
              <FieldLabel htmlFor="filter-status">Status</FieldLabel>
              <Select
                value={filters.status || 'all'}
                onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}
              >
                <SelectTrigger id="filter-status" className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="scheduled">Scheduled</SelectItem>
                  <SelectItem value="sending">Sending</SelectItem>
                  <SelectItem value="paused">Paused</SelectItem>
                  <SelectItem value="sent">Sent</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel htmlFor="filter-channel">Channel</FieldLabel>
              <Select
                value={filters.channel || 'all'}
                onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}
              >
                <SelectTrigger id="filter-channel" className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  {Object.entries(CHANNEL_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
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
                  <TableHead className="w-28">Actions</TableHead>
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
                          className="text-left hover:underline"
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
                        <div className="flex items-center gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0"
                            onClick={() => setView({ mode: 'detail', campaign })}
                            title="View"
                          >
                            <Eye className="h-3.5 w-3.5" />
                          </Button>
                          {canEdit && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0"
                              onClick={() => setView({ mode: 'edit', campaign })}
                              title="Edit"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </Button>
                          )}
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0"
                            onClick={() => void handleDuplicate(campaign.id)}
                            disabled={duplicating === campaign.id}
                            title="Duplicate"
                          >
                            <Copy className="h-3.5 w-3.5" />
                          </Button>
                          {canDelete && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                              onClick={() => void handleDelete(campaign.id)}
                              disabled={deleting === campaign.id}
                              title="Delete"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </DataTable>
      </PageSection>
    </div>
  );
}
