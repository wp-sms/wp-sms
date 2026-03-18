import { Fragment, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { PageNumbers } from '@/components/ui/pagination';
import { Send, ChevronRight, ChevronDown, ScrollText } from 'lucide-react';
import { useMessageLogs } from '@/hooks/use-message-logs';
import { formatLabel } from '@/lib/constants';
import type { MessageLogEntry } from '@/lib/api';

function StatusBadge({ status }: { status: string }) {
  switch (status) {
    case 'delivered':
      return <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700">{status}</Badge>;
    case 'sent':
      return <Badge variant="outline" className="border-blue-200 bg-blue-50 text-blue-700">{status}</Badge>;
    case 'failed':
      return <Badge variant="destructive">{status}</Badge>;
    default:
      return <Badge variant="outline">{status}</Badge>;
  }
}

function ChannelBadge({ channel }: { channel: string }) {
  return <Badge variant="secondary">{formatLabel(channel)}</Badge>;
}

function DetailRow({ label, value, className }: { label: string; value: string; className?: string }) {
  return (
    <div className="flex gap-2">
      <span className="font-medium text-muted-foreground">{label}:</span>
      <span className={className}>{value}</span>
    </div>
  );
}

function hasDetails(log: MessageLogEntry): boolean {
  return !!(log.body_preview || log.error || log.subject || log.provider_id || log.execution_id || log.type);
}

export function MessageLogs() {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading } = useMessageLogs();
  const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

  const toggleRow = (id: string) => {
    setExpandedRows((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Send className="h-5 w-5" />
          Message Logs
        </CardTitle>
        <CardDescription>
          {total > 0 ? `Showing ${total} message${total !== 1 ? 's' : ''}` : 'No messages yet'}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Filters */}
        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <Select value={filters.channel || 'all'} onValueChange={(v) => setFilter('channel', v === 'all' ? '' : v)}>
            <SelectTrigger>
              <SelectValue placeholder="Channel" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Channels</SelectItem>
              <SelectItem value="sms">SMS</SelectItem>
              <SelectItem value="email">Email</SelectItem>
              <SelectItem value="webhook">Webhook</SelectItem>
              <SelectItem value="telegram">Telegram</SelectItem>
            </SelectContent>
          </Select>

          <Select value={filters.status || 'all'} onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}>
            <SelectTrigger>
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="sent">Sent</SelectItem>
              <SelectItem value="delivered">Delivered</SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>

          <Input
            placeholder="Search recipient..."
            value={filters.recipient}
            onChange={(e) => setFilter('recipient', e.target.value)}
          />

          <Input
            placeholder="Filter by gateway"
            value={filters.gateway_id}
            onChange={(e) => setFilter('gateway_id', e.target.value)}
          />

          <Input
            type="date"
            placeholder="From"
            value={filters.date_from}
            onChange={(e) => setFilter('date_from', e.target.value)}
          />

          <Input
            type="date"
            placeholder="To"
            value={filters.date_to}
            onChange={(e) => setFilter('date_to', e.target.value)}
          />
        </div>

        {/* Table / Loading / Empty */}
        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : logs.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
              <ScrollText className="h-5 w-5 text-muted-foreground" />
            </div>
            <p className="text-sm font-medium text-foreground">No messages logged yet</p>
            <p className="mt-1 text-xs text-muted-foreground">Messages will appear here as they are sent.</p>
          </div>
        ) : (
          <div>
            <div className="rounded-lg border border-border/50 overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-8"></TableHead>
                    <TableHead>Channel</TableHead>
                    <TableHead>Recipient</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Gateway</TableHead>
                    <TableHead>Cost</TableHead>
                    <TableHead>Date</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {logs.map((log) => {
                    const expandable = hasDetails(log);
                    const isExpanded = expandedRows.has(log.id);

                    return (
                      <Fragment key={log.id}>
                        <TableRow
                          className={`even:bg-muted/30 ${expandable ? 'cursor-pointer' : ''}`}
                          onClick={expandable ? () => toggleRow(log.id) : undefined}
                        >
                          <TableCell className="w-8 px-2">
                            {expandable && (
                              <button
                                type="button"
                                aria-label={isExpanded ? 'Collapse details' : 'Expand details'}
                                className="p-0.5 text-muted-foreground hover:text-foreground"
                              >
                                {isExpanded ? (
                                  <ChevronDown className="h-4 w-4" />
                                ) : (
                                  <ChevronRight className="h-4 w-4" />
                                )}
                              </button>
                            )}
                          </TableCell>
                          <TableCell><ChannelBadge channel={log.channel} /></TableCell>
                          <TableCell className="font-mono text-xs">{log.recipient}</TableCell>
                          <TableCell><StatusBadge status={log.status} /></TableCell>
                          <TableCell className="text-xs">{log.gateway_id}</TableCell>
                          <TableCell className="text-xs">{log.cost ?? '\u2014'}</TableCell>
                          <TableCell className="text-sm">{new Date(log.created_at).toLocaleString()}</TableCell>
                        </TableRow>
                        {isExpanded && (
                          <TableRow key={`${log.id}-details`} className="bg-muted/20 hover:bg-muted/20">
                            <TableCell />
                            <TableCell colSpan={6} className="whitespace-normal">
                              <div className="grid gap-2 py-1 text-xs">
                                {log.type && <DetailRow label="Type" value={formatLabel(log.type)} />}
                                {log.subject && <DetailRow label="Subject" value={log.subject} />}
                                {log.body_preview && <DetailRow label="Message" value={log.body_preview} />}
                                {log.provider_id && <DetailRow label="Provider ID" value={log.provider_id} />}
                                {log.error && <DetailRow label="Error" value={log.error} className="text-destructive" />}
                                {log.execution_id && <DetailRow label="Flow Execution" value={log.execution_id} />}
                                {log.sent_at && <DetailRow label="Sent At" value={new Date(log.sent_at).toLocaleString()} />}
                                {log.delivered_at && <DetailRow label="Delivered At" value={new Date(log.delivered_at).toLocaleString()} />}
                              </div>
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    );
                  })}
                </TableBody>
              </Table>
            </div>

            <PageNumbers page={page} totalPages={Math.ceil(total / perPage)} onPageChange={setPage} />
          </div>
        )}
      </CardContent>
    </Card>
  );
}
