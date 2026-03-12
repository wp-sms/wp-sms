import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Settings2, List } from 'lucide-react';
import { LogTable } from '@/components/log-table';
import { useLogs } from '@/hooks/use-logs';
import { EVENT_TYPES, LOG_VERBOSITY, formatLabel } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface LogsPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function LogsPage({ settings, onUpdate }: LogsPageProps) {
  const { logs, total, page, perPage, filters, setFilter, setPage, loading } = useLogs();

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
                onValueChange={(value) => onUpdate('log_verbosity', value)}
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
          <CardTitle className="flex items-center gap-2 text-base">
            <List className="h-4 w-4 text-muted-foreground" />
            Event Log
          </CardTitle>
          <CardDescription>
            Showing {total} total events
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-4 grid gap-4 sm:grid-cols-3">
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
