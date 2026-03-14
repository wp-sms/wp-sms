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
import { Skeleton } from '@/components/ui/skeleton';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import { ScrollText, ChevronRight, ChevronDown } from 'lucide-react';
import type { LogEntry } from '@/lib/api';
import { formatLabel } from '@/lib/constants';

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

  if (loading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (logs.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
          <ScrollText className="h-5 w-5 text-muted-foreground" />
        </div>
        <p className="text-sm font-medium text-foreground">No log entries found</p>
        <p className="mt-1 text-xs text-muted-foreground">Events will appear here as users authenticate.</p>
      </div>
    );
  }

  return (
    <div>
      <div className="rounded-lg border border-border/50 overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-8"></TableHead>
              <TableHead>Event</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>User</TableHead>
              <TableHead>IP Address</TableHead>
              <TableHead>Date</TableHead>
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
                    <TableCell className="font-medium">{formatLabel(log.event)}</TableCell>
                    <TableCell>
                      {log.status === 'success' ? (
                        <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700">
                          {log.status}
                        </Badge>
                      ) : (
                        <Badge variant="destructive">
                          {log.status}
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      {log.user_display
                        ? <span title={log.user_display.email}>{log.user_display.display_name}</span>
                        : log.user_id || '\u2014'}
                    </TableCell>
                    <TableCell className="font-mono text-xs">{log.ip_address}</TableCell>
                    <TableCell className="text-sm">{new Date(log.created_at).toLocaleString()}</TableCell>
                  </TableRow>
                  {isExpanded && (
                    <TableRow key={`${log.id}-details`} className="bg-muted/20 hover:bg-muted/20">
                      <TableCell />
                      <TableCell colSpan={5} className="whitespace-normal">
                        <div className="grid gap-2 py-1 text-xs">
                          {log.channel_id && (
                            <div className="flex gap-2">
                              <span className="font-medium text-muted-foreground">Channel:</span>
                              <span>{log.channel_id}</span>
                            </div>
                          )}
                          {log.user_agent && (
                            <div className="flex gap-2">
                              <span className="font-medium text-muted-foreground">User Agent:</span>
                              <span className="break-all">{log.user_agent}</span>
                            </div>
                          )}
                          {meta && Object.keys(meta).length > 0 && (
                            <div className="flex flex-col gap-1">
                              <span className="font-medium text-muted-foreground">Metadata:</span>
                              <div className="grid gap-1 pl-2">
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
      </div>

      {totalPages > 1 && (
        <div className="mt-4 flex justify-center">
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  onClick={() => onPageChange(Math.max(1, page - 1))}
                  className={page <= 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
              {Array.from({ length: Math.min(5, totalPages) }).map((_, i) => {
                let pageNum: number;
                if (totalPages <= 5) {
                  pageNum = i + 1;
                } else if (page <= 3) {
                  pageNum = i + 1;
                } else if (page >= totalPages - 2) {
                  pageNum = totalPages - 4 + i;
                } else {
                  pageNum = page - 2 + i;
                }
                return (
                  <PaginationItem key={pageNum}>
                    <PaginationLink
                      onClick={() => onPageChange(pageNum)}
                      isActive={page === pageNum}
                      className="cursor-pointer"
                    >
                      {pageNum}
                    </PaginationLink>
                  </PaginationItem>
                );
              })}
              <PaginationItem>
                <PaginationNext
                  onClick={() => onPageChange(Math.min(totalPages, page + 1))}
                  className={page >= totalPages ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </div>
      )}
    </div>
  );
}
