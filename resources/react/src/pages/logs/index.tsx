import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Settings2, List, Trash2 } from 'lucide-react';
import { LogTable } from '@/components/log-table';
import { useLogs } from '@/hooks/use-logs';
import { EVENT_TYPES, LOG_VERBOSITY, formatLabel } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface LogsPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function LogsPage({ settings, onUpdate }: LogsPageProps) {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading, clearLogs } = useLogs();
  const [confirmClear, setConfirmClear] = useState(false);
  const [clearing, setClearing] = useState(false);

  const handleClearLogs = async () => {
    setClearing(true);
    try {
      await clearLogs();
    } finally {
      setClearing(false);
      setConfirmClear(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Settings2 className="h-4 w-4 text-muted-foreground" />
            Log Settings
          </CardTitle>
          <CardDescription>
            Configure what gets logged and how long logs are retained
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field>
              <FieldLabel htmlFor="log_verbosity">Verbosity</FieldLabel>
              <Select
                value={settings.log_verbosity}
                onValueChange={(value) => onUpdate('log_verbosity', value as AuthSettings['log_verbosity'])}
              >
                <SelectTrigger id="log_verbosity">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {LOG_VERBOSITY.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldDescription>
                {LOG_VERBOSITY.find((v) => v.value === settings.log_verbosity)?.description}
              </FieldDescription>
            </Field>
            <Field>
              <FieldLabel htmlFor="log_retention_days">Retention (days)</FieldLabel>
              <Input
                id="log_retention_days"
                type="number"
                min={1}
                max={365}
                value={settings.log_retention_days}
                onChange={(e) => onUpdate('log_retention_days', Number(e.target.value))}
              />
              <FieldDescription>How long to keep log entries before cleanup</FieldDescription>
            </Field>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <List className="h-4 w-4 text-muted-foreground" />
                Event Log
              </CardTitle>
              <CardDescription>
                Showing {total} total events
              </CardDescription>
            </div>
            {total > 0 && (
              <div className="flex items-center gap-2">
                {confirmClear ? (
                  <>
                    <span className="text-sm text-destructive">Delete all logs?</span>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={handleClearLogs}
                      disabled={clearing}
                    >
                      {clearing ? 'Deleting...' : 'Confirm'}
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setConfirmClear(false)}
                      disabled={clearing}
                    >
                      Cancel
                    </Button>
                  </>
                ) : (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setConfirmClear(true)}
                    className="text-destructive hover:text-destructive"
                  >
                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                    Clear Logs
                  </Button>
                )}
              </div>
            )}
          </div>
        </CardHeader>
        <CardContent>
          <div className="mb-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <Field>
              <FieldLabel htmlFor="filter-event">Event Type</FieldLabel>
              <Select
                value={filters.event || 'all'}
                onValueChange={(value) => setFilter('event', value === 'all' ? '' : value)}
              >
                <SelectTrigger id="filter-event">
                  <SelectValue placeholder="All events" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All events</SelectItem>
                  {EVENT_TYPES.map((evt) => (
                    <SelectItem key={evt} value={evt}>
                      {formatLabel(evt)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel htmlFor="filter-status">Status</FieldLabel>
              <Select
                value={filters.status || 'all'}
                onValueChange={(value) => setFilter('status', value === 'all' ? '' : value)}
              >
                <SelectTrigger id="filter-status">
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="success">Success</SelectItem>
                  <SelectItem value="failure">Failure</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel htmlFor="filter-user">User ID</FieldLabel>
              <Input
                id="filter-user"
                type="text"
                placeholder="Filter by user ID"
                value={filters.user_id}
                onChange={(e) => setFilter('user_id', e.target.value)}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="filter-date-from">From Date</FieldLabel>
              <Input
                id="filter-date-from"
                type="date"
                value={filters.date_from}
                onChange={(e) => setFilter('date_from', e.target.value)}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="filter-date-to">To Date</FieldLabel>
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
        </CardContent>
      </Card>
    </div>
  );
}
