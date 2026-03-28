import { __ } from '@wordpress/i18n';
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
import { NameCell } from '@/components/ui/name-cell';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { CHANNEL_LABELS } from '@/components/gateway-config-form';
import { Plus, Megaphone, Pencil, Trash2, Copy, Eye } from 'lucide-react';
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
      title: __('Delete campaign?', 'wp-sms'),
      description: __('This campaign and its delivery history will be permanently removed.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    setDeleting(id);
    try {
      await deleteCampaign(id);
      toast.success(__('Campaign deleted.', 'wp-sms'));
    } catch {
      toast.error(__('Failed to delete campaign.', 'wp-sms'));
    } finally {
      setDeleting(null);
    }
  };

  const handleDuplicate = async (id: string) => {
    setDuplicating(id);
    try {
      const clone = await duplicateCampaign(id);
      toast.success(__('Campaign duplicated.', 'wp-sms'));
      setView({ mode: 'edit', campaign: clone });
    } catch {
      toast.error(__('Failed to duplicate campaign.', 'wp-sms'));
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
        title={__('Campaigns', 'wp-sms')}
        metadata={pluralize(total, 'campaign')}
        actions={
          <Button size="sm" onClick={() => setView({ mode: 'create' })}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            {__('New Campaign', 'wp-sms')}
          </Button>
        }
      />
      <div className="flex items-center gap-3">
        <Select
          value={filters.status || 'all'}
          onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}
        >
          <SelectTrigger className="w-40">
            <SelectValue placeholder={__('All Statuses', 'wp-sms')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{__('All Statuses', 'wp-sms')}</SelectItem>
            <SelectItem value="draft">{__('Draft', 'wp-sms')}</SelectItem>
            <SelectItem value="scheduled">{__('Scheduled', 'wp-sms')}</SelectItem>
            <SelectItem value="sending">{__('Sending', 'wp-sms')}</SelectItem>
            <SelectItem value="paused">{__('Paused', 'wp-sms')}</SelectItem>
            <SelectItem value="sent">{__('Sent', 'wp-sms')}</SelectItem>
            <SelectItem value="cancelled">{__('Cancelled', 'wp-sms')}</SelectItem>
            <SelectItem value="failed">{__('Failed', 'wp-sms')}</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={filters.channel || 'all'}
          onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}
        >
          <SelectTrigger className="w-40">
            <SelectValue placeholder={__('All Channels', 'wp-sms')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{__('All Channels', 'wp-sms')}</SelectItem>
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
            {__('Clear filters', 'wp-sms')}
          </Button>
        )}
      </div>

      <DataTable
        loading={loading}
        isEmpty={campaigns.length === 0}
        empty={
          <EmptyState
            icon={Megaphone}
            title={__('No campaigns found', 'wp-sms')}
            description={__('Create your first campaign to start broadcasting messages.', 'wp-sms')}
            action={
              <Button size="sm" onClick={() => setView({ mode: 'create' })}>
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                {__('New Campaign', 'wp-sms')}
              </Button>
            }
          />
        }
        pagination={{ page, totalPages, onPageChange: setPage }}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Name', 'wp-sms')}</TableHead>
              <TableHead>{__('Channel', 'wp-sms')}</TableHead>
              <TableHead>{__('Status', 'wp-sms')}</TableHead>
              <TableHead className="text-right">{__('Recipients', 'wp-sms')}</TableHead>
              <TableHead className="text-right">{__('Sent', 'wp-sms')}</TableHead>
              <TableHead>{__('Scheduled', 'wp-sms')}</TableHead>
              <TableHead className="w-[70px]" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {campaigns.map((campaign) => {
              const canEdit = campaign.status === 'draft' || campaign.status === 'scheduled';
              const canDelete = campaign.status !== 'sending';
              return (
                <TableRow key={campaign.id} className="even:bg-muted/30">
                  <NameCell onClick={() => setView({ mode: 'detail', campaign })}>
                    {campaign.name}
                  </NameCell>
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
                  <ActionsCell>
                    <DropdownMenuItem onClick={() => setView({ mode: 'detail', campaign })}>
                      <Eye className="h-4 w-4 mr-2" />
                      {__('View', 'wp-sms')}
                    </DropdownMenuItem>
                    {canEdit && (
                      <DropdownMenuItem onClick={() => setView({ mode: 'edit', campaign })}>
                        <Pencil className="h-4 w-4 mr-2" />
                        {__('Edit', 'wp-sms')}
                      </DropdownMenuItem>
                    )}
                    <DropdownMenuItem
                      onClick={() => void handleDuplicate(campaign.id)}
                      disabled={duplicating === campaign.id}
                    >
                      <Copy className="h-4 w-4 mr-2" />
                      {__('Duplicate', 'wp-sms')}
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
                          {__('Delete', 'wp-sms')}
                        </DropdownMenuItem>
                      </>
                    )}
                  </ActionsCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}
