import { __ } from '@wordpress/i18n';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { useState, useEffect, type ReactNode } from 'react';
import { PageHeader } from '@/components/layout/page-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { Send, ScrollText, SlidersHorizontal } from 'lucide-react';
import { pluralize } from '@/lib/utils';
import { useMessageLogs } from '@/hooks/use-message-logs';
import { StatusBadge, ChannelBadge } from '@/components/messaging/message-badges';
import { MessageLogDetailPanel } from '@/components/messaging/message-log-detail-panel';
import type { MessageLogEntry } from '@/lib/api';

interface MessageLogsProps {
  embedded?: boolean;
  setHeaderMeta?: (node: ReactNode) => void;
  setHeaderActions?: (node: ReactNode) => void;
}

export function MessageLogs({ embedded, setHeaderMeta, setHeaderActions }: MessageLogsProps) {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading } = useMessageLogs();
  const [selectedLog, setSelectedLog] = useState<MessageLogEntry | null>(null);
  const [filtersOpen, setFiltersOpen] = useState(false);

  const activeFilterCount = [filters.channel, filters.status, filters.recipient, filters.gateway_id, filters.date_from, filters.date_to].filter(Boolean).length;

  const filtersButton = (
    <Button variant="outline" size="sm" onClick={() => setFiltersOpen(v => !v)}>
      <SlidersHorizontal className="mr-1.5 h-3.5 w-3.5" />
      Filters
      {activeFilterCount > 0 && (
        <Badge variant="default" className="ml-1.5 h-5 px-1.5 text-[10px]">{activeFilterCount}</Badge>
      )}
    </Button>
  );

  useEffect(() => {
    if (!setHeaderMeta) return;
    setHeaderMeta(pluralize(total, 'message'));
    return () => setHeaderMeta(null);
  }, [total, setHeaderMeta]);

  useEffect(() => {
    if (!setHeaderActions) return;
    setHeaderActions(filtersButton);
    return () => setHeaderActions(null);
  }, [activeFilterCount, setHeaderActions]);

  return (
    <Collapsible open={filtersOpen} onOpenChange={setFiltersOpen}>
      <div className="space-y-4">
        {!embedded && (
          <PageHeader icon={Send} title={__('Message Logs', 'wp-sms')} metadata={pluralize(total, 'message')} actions={filtersButton} />
        )}
        {embedded && !setHeaderMeta && (
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">{pluralize(total, 'message')}</span>
            {filtersButton}
          </div>
        )}
        <CollapsibleContent>
          <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <Field>
              <FieldLabel htmlFor="filter-channel">Channel</FieldLabel>
              <Select value={filters.channel || 'all'} onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}>
                <SelectTrigger id="filter-channel">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Channels', 'wp-sms')}</SelectItem>
                  <SelectItem value="sms">{__('SMS', 'wp-sms')}</SelectItem>
                  <SelectItem value="email">{__('Email', 'wp-sms')}</SelectItem>
                  <SelectItem value="webhook">{__('Webhook', 'wp-sms')}</SelectItem>
                  <SelectItem value="telegram">{__('Telegram', 'wp-sms')}</SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel htmlFor="filter-status">Status</FieldLabel>
              <Select value={filters.status || 'all'} onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}>
                <SelectTrigger id="filter-status">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Statuses', 'wp-sms')}</SelectItem>
                  <SelectItem value="pending">{__('Pending', 'wp-sms')}</SelectItem>
                  <SelectItem value="sent">{__('Sent', 'wp-sms')}</SelectItem>
                  <SelectItem value="delivered">{__('Delivered', 'wp-sms')}</SelectItem>
                  <SelectItem value="failed">{__('Failed', 'wp-sms')}</SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel htmlFor="filter-recipient">Recipient</FieldLabel>
              <Input
                id="filter-recipient"
                placeholder={__('Search recipient...', 'wp-sms')}
                value={filters.recipient}
                onChange={(e) => setFilter('recipient', e.target.value)}
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="filter-gateway">Gateway</FieldLabel>
              <Input
                id="filter-gateway"
                placeholder={__('Filter by gateway', 'wp-sms')}
                value={filters.gateway_id}
                onChange={(e) => setFilter('gateway_id', e.target.value)}
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="filter-from">From</FieldLabel>
              <Input
                id="filter-from"
                type="date"
                value={filters.date_from}
                onChange={(e) => setFilter('date_from', e.target.value)}
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="filter-to">To</FieldLabel>
              <Input
                id="filter-to"
                type="date"
                value={filters.date_to}
                onChange={(e) => setFilter('date_to', e.target.value)}
              />
            </Field>
          </div>
        </CollapsibleContent>

        <DataTable
          loading={loading}
          isEmpty={logs.length === 0}
          empty={
            <EmptyState
              icon={ScrollText}
              title={__('No messages logged yet', 'wp-sms')}
              description={__('Messages will appear here as they are sent.', 'wp-sms')}
            />
          }
          pagination={{ page, totalPages: Math.ceil(total / perPage), onPageChange: setPage }}
        >
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{__('Channel', 'wp-sms')}</TableHead>
                <TableHead>{__('Recipient', 'wp-sms')}</TableHead>
                <TableHead>{__('Status', 'wp-sms')}</TableHead>
                <TableHead>{__('Gateway', 'wp-sms')}</TableHead>
                <TableHead>{__('Cost', 'wp-sms')}</TableHead>
                <TableHead>{__('Date', 'wp-sms')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.map((log) => (
                <TableRow
                  key={log.id}
                  className={`cursor-pointer even:bg-muted/30 ${selectedLog?.id === log.id ? 'bg-accent' : ''}`}
                  onClick={() => setSelectedLog(log)}
                >
                  <TableCell><ChannelBadge channel={log.channel} /></TableCell>
                  <TableCell className="font-mono text-xs">{log.recipient}</TableCell>
                  <TableCell><StatusBadge status={log.status} /></TableCell>
                  <TableCell className="text-xs">{log.gateway_id}</TableCell>
                  <TableCell className="text-xs">{log.cost ?? '\u2014'}</TableCell>
                  <TableCell className="text-sm">
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <span className="cursor-default">{formatRelativeTime(log.created_at)}</span>
                      </TooltipTrigger>
                      <TooltipContent>{formatDateTime(log.created_at)}</TooltipContent>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DataTable>
      </div>

      <MessageLogDetailPanel log={selectedLog} onClose={() => setSelectedLog(null)} />
    </Collapsible>
  );
}
