import { __ } from '@wordpress/i18n';
import { useState, useEffect, type ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Trash2, ScrollText } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { useConfirm } from '@/components/confirm-provider';
import { LogTable } from '@/components/log-table';
import { useLogs } from '@/hooks/use-logs';
import { EVENT_TYPES, formatLabel } from '@/lib/constants';
import { pluralize } from '@/lib/utils';

interface LogsPageProps {
  embedded?: boolean;
  setHeaderMeta?: (node: ReactNode) => void;
  setHeaderActions?: (node: ReactNode) => void;
}

export function LogsPage({ embedded, setHeaderMeta, setHeaderActions }: LogsPageProps) {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading, clearLogs } = useLogs();
  const confirm = useConfirm();
  const [clearing, setClearing] = useState(false);

  const handleClearLogs = async () => {
    const ok = await confirm({
      title: __('Clear all logs?', 'wp-sms'),
      description: __('This will permanently delete all event log entries. This action cannot be undone.', 'wp-sms'),
      confirmLabel: __('Clear Logs', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    setClearing(true);
    try {
      await clearLogs();
    } finally {
      setClearing(false);
    }
  };

  const clearButton = total > 0 ? (
    <Button
      variant="outline"
      size="sm"
      onClick={handleClearLogs}
      disabled={clearing}
      className="text-destructive hover:text-destructive"
    >
      <Trash2 className="me-1 h-3.5 w-3.5" />
      {clearing ? __('Clearing...', 'wp-sms') : __('Clear Logs', 'wp-sms')}
    </Button>
  ) : undefined;

  useEffect(() => {
    if (!setHeaderMeta) return;
    setHeaderMeta(!loading ? pluralize(total, 'event') : null);
    return () => setHeaderMeta(null);
  }, [total, loading, setHeaderMeta]);

  useEffect(() => {
    if (!setHeaderActions) return;
    setHeaderActions(clearButton ?? null);
    return () => setHeaderActions(null);
  }, [total, clearing, setHeaderActions]);

  return (
    <div className="space-y-4">
      {!embedded && (
        <PageHeader
          icon={ScrollText}
          title={__('Logs', 'wp-sms')}
          metadata={!loading ? pluralize(total, 'event') : undefined}
          actions={clearButton}
        />
      )}
      {embedded && !setHeaderMeta && clearButton && (
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">{pluralize(total, 'event')}</span>
          {clearButton}
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <Field>
          <FieldLabel htmlFor="filter-event">{__('Event Type', 'wp-sms')}</FieldLabel>
          <Select
            value={filters.event || 'all'}
            onValueChange={(value) => setFilter('event', value === 'all' ? '' : value)}
          >
            <SelectTrigger id="filter-event">
              <SelectValue placeholder={__('All events', 'wp-sms')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All events', 'wp-sms')}</SelectItem>
              {EVENT_TYPES.map((evt) => (
                <SelectItem key={evt} value={evt}>
                  {formatLabel(evt)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field>
          <FieldLabel htmlFor="filter-status">{__('Status', 'wp-sms')}</FieldLabel>
          <Select
            value={filters.status || 'all'}
            onValueChange={(value) => setFilter('status', value === 'all' ? '' : value)}
          >
            <SelectTrigger id="filter-status">
              <SelectValue placeholder={__('All statuses', 'wp-sms')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All statuses', 'wp-sms')}</SelectItem>
              <SelectItem value="success">{__('Success', 'wp-sms')}</SelectItem>
              <SelectItem value="failure">{__('Failure', 'wp-sms')}</SelectItem>
            </SelectContent>
          </Select>
        </Field>
        <Field>
          <FieldLabel htmlFor="filter-user">{__('User ID', 'wp-sms')}</FieldLabel>
          <Input
            id="filter-user"
            type="text"
            dir="ltr"
            placeholder={__('Filter by user ID', 'wp-sms')}
            value={filters.user_id}
            onChange={(e) => setFilter('user_id', e.target.value)}
          />
        </Field>
        <Field>
          <FieldLabel htmlFor="filter-date-from">{__('From Date', 'wp-sms')}</FieldLabel>
          <Input
            id="filter-date-from"
            type="date"
            value={filters.date_from}
            onChange={(e) => setFilter('date_from', e.target.value)}
          />
        </Field>
        <Field>
          <FieldLabel htmlFor="filter-date-to">{__('To Date', 'wp-sms')}</FieldLabel>
          <Input
            id="filter-date-to"
            type="date"
            value={filters.date_to}
            onChange={(e) => setFilter('date_to', e.target.value)}
          />
        </Field>
      </div>

      <LogTable
        logs={logs}
        total={total}
        page={page}
        perPage={perPage}
        onPageChange={setPage}
        loading={loading}
      />
    </div>
  );
}
