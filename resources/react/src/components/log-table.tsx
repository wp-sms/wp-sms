import { __ } from '@wordpress/i18n';
import { formatDateTime } from '@/lib/format';
import { Fragment, useState } from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { ScrollText, ChevronRight, ChevronDown } from 'lucide-react';
import type { LogEntry } from '@/lib/api';
import { formatLabel } from '@/lib/constants';
import { formatCountry } from '@/lib/country';

interface LogTableProps {
  logs: LogEntry[];
  total: number;
  page: number;
  perPage: number;
  onPageChange: (page: number) => void;
  loading: boolean;
}

function hasDetails(log: LogEntry): boolean {
  return !!(log.meta || log.user_agent || log.channel_id);
}

function parseMeta(log: LogEntry): Record<string, unknown> | null {
  if (!log.meta) return null;
  if (typeof log.meta === 'string') {
    try {
      return JSON.parse(log.meta);
    } catch {
      return null;
    }
  }
  return log.meta;
}

export function LogTable({ logs, total, page, perPage, onPageChange, loading }: LogTableProps) {
  const totalPages = Math.ceil(total / perPage);
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

  const toggleRow = (id: number) => {
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
    <DataTable
      loading={loading}
      isEmpty={logs.length === 0}
      empty={
        <EmptyState
          icon={ScrollText}
          title={__('No log entries found', 'wp-sms')}
          description={__('Events will appear here as users authenticate.', 'wp-sms')}
        />
      }
      pagination={{ page, totalPages, onPageChange: onPageChange }}
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-8"></TableHead>
            <TableHead>{__('Event', 'wp-sms')}</TableHead>
            <TableHead>{__('Status', 'wp-sms')}</TableHead>
            <TableHead>{__('User', 'wp-sms')}</TableHead>
            <TableHead>{__('IP Address', 'wp-sms')}</TableHead>
            <TableHead>{__('Country', 'wp-sms')}</TableHead>
            <TableHead>{__('Date', 'wp-sms')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {logs.map((log) => {
            const expandable = hasDetails(log);
            const isExpanded = expandedRows.has(log.id);
            const meta = isExpanded ? parseMeta(log) : null;

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
                        aria-label={isExpanded ? __('Collapse details', 'wp-sms') : __('Expand details', 'wp-sms')}
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
                  <TableCell className="font-medium">{formatLabel(log.event)}</TableCell>
                  <TableCell>
                    <Badge variant={log.status === 'success' ? 'success' : 'destructive'}>
                      {log.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {log.user_display
                      ? <span title={log.user_display.email}>{log.user_display.display_name}</span>
                      : log.user_id || '\u2014'}
                  </TableCell>
                  <TableCell className="font-mono text-xs">{log.ip_address}</TableCell>
                  <TableCell className="text-sm">{formatCountry(log.geo_country)}</TableCell>
                  <TableCell className="text-sm">{formatDateTime(log.created_at)}</TableCell>
                </TableRow>
                {isExpanded && (
                  <TableRow key={`${log.id}-details`} className="bg-muted/20 hover:bg-muted/20">
                    <TableCell />
                    <TableCell colSpan={6} className="whitespace-normal">
                      <div className="grid gap-2 py-1 text-xs">
                        {log.channel_id && (
                          <div className="flex gap-2">
                            <span className="font-medium text-muted-foreground">{__('Channel:', 'wp-sms')}</span>
                            <span>{log.channel_id}</span>
                          </div>
                        )}
                        {log.user_agent && (
                          <div className="flex gap-2">
                            <span className="font-medium text-muted-foreground">{__('User Agent:', 'wp-sms')}</span>
                            <span className="break-all">{log.user_agent}</span>
                          </div>
                        )}
                        {meta && Object.keys(meta).length > 0 && (
                          <div className="flex flex-col gap-1">
                            <span className="font-medium text-muted-foreground">{__('Metadata:', 'wp-sms')}</span>
                            <div className="grid gap-1 ps-2">
                              {Object.entries(meta).map(([key, value]) => (
                                <div key={key} className="flex gap-2">
                                  <span className="font-medium text-muted-foreground">{formatLabel(key)}:</span>
                                  <span className="break-all">{typeof value === 'object' ? JSON.stringify(value) : String(value)}</span>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </Fragment>
            );
          })}
        </TableBody>
      </Table>
    </DataTable>
  );
}
