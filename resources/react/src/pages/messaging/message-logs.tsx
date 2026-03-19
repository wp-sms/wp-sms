import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { PageNumbers } from '@/components/ui/pagination';
import { Send, ScrollText } from 'lucide-react';
import { useMessageLogs } from '@/hooks/use-message-logs';
import { StatusBadge, ChannelBadge } from '@/components/messaging/message-badges';
import { MessageLogDetailDrawer } from '@/components/messaging/message-log-detail-drawer';
import type { MessageLogEntry } from '@/lib/api';

export function MessageLogs() {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading } = useMessageLogs();
  const [selectedLog, setSelectedLog] = useState<MessageLogEntry | null>(null);

  return (
    <>
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
                        <TableCell className="text-sm">{new Date(log.created_at).toLocaleString()}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <PageNumbers page={page} totalPages={Math.ceil(total / perPage)} onPageChange={setPage} />
            </div>
          )}
        </CardContent>
      </Card>

      <MessageLogDetailDrawer log={selectedLog} onClose={() => setSelectedLog(null)} />
    </>
  );
}
