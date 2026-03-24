import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { useState } from 'react';
import { PageSection } from '@/components/ui/page-section';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { Send, ScrollText, SlidersHorizontal } from 'lucide-react';
import { useMessageLogs } from '@/hooks/use-message-logs';
import { StatusBadge, ChannelBadge } from '@/components/messaging/message-badges';
import { MessageLogDetailPanel } from '@/components/messaging/message-log-detail-panel';
import type { MessageLogEntry } from '@/lib/api';

export function MessageLogs() {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading } = useMessageLogs();
  const [selectedLog, setSelectedLog] = useState<MessageLogEntry | null>(null);
  const [filtersOpen, setFiltersOpen] = useState(false);

  const activeFilterCount = [filters.channel, filters.status, filters.recipient, filters.gateway_id, filters.date_from, filters.date_to].filter(Boolean).length;

  return (
    <>
      <PageSection
        icon={Send}
        title="Message Logs"
        description={total > 0 ? `Showing ${total} message${total !== 1 ? 's' : ''}` : 'No messages yet'}
        contentClassName="space-y-4"
      >
          {/* Collapsible Filters */}
          <Collapsible open={filtersOpen} onOpenChange={setFiltersOpen}>
            <CollapsibleTrigger asChild>
              <Button variant="outline" size="sm">
                <SlidersHorizontal className="mr-1.5 h-3.5 w-3.5" />
                Filters
                {activeFilterCount > 0 && (
                  <Badge variant="default" className="ml-1.5 h-5 px-1.5 text-[10px]">{activeFilterCount}</Badge>
                )}
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <div className="mt-3 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <Field>
                  <FieldLabel htmlFor="filter-channel">Channel</FieldLabel>
                  <Select value={filters.channel || 'all'} onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}>
                    <SelectTrigger id="filter-channel">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Channels</SelectItem>
                      <SelectItem value="sms">SMS</SelectItem>
                      <SelectItem value="email">Email</SelectItem>
                      <SelectItem value="webhook">Webhook</SelectItem>
                      <SelectItem value="telegram">Telegram</SelectItem>
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
                      <SelectItem value="all">All Statuses</SelectItem>
                      <SelectItem value="pending">Pending</SelectItem>
                      <SelectItem value="sent">Sent</SelectItem>
                      <SelectItem value="delivered">Delivered</SelectItem>
                      <SelectItem value="failed">Failed</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>

                <Field>
                  <FieldLabel htmlFor="filter-recipient">Recipient</FieldLabel>
                  <Input
                    id="filter-recipient"
                    placeholder="Search recipient..."
                    value={filters.recipient}
                    onChange={(e) => setFilter('recipient', e.target.value)}
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="filter-gateway">Gateway</FieldLabel>
                  <Input
                    id="filter-gateway"
                    placeholder="Filter by gateway"
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
          </Collapsible>

          {/* Table / Loading / Empty */}
          <DataTable
            loading={loading}
            isEmpty={logs.length === 0}
            empty={
              <EmptyState
                icon={ScrollText}
                title="No messages logged yet"
                description="Messages will appear here as they are sent."
              />
            }
            pagination={{ page, totalPages: Math.ceil(total / perPage), onPageChange: setPage }}
          >
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Channel</TableHead>
                  <TableHead>Recipient</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Gateway</TableHead>
                  <TableHead>Cost</TableHead>
                  <TableHead>Date</TableHead>
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
      </PageSection>

      <MessageLogDetailPanel log={selectedLog} onClose={() => setSelectedLog(null)} />
    </>
  );
}
