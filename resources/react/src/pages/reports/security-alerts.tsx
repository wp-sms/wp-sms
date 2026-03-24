import { formatDateTime } from '@/lib/format';
import { PageSection } from '@/components/ui/page-section';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { ShieldAlert } from 'lucide-react';
import { fmtNumber } from '@/lib/utils';
import type { ReportsResponse } from '@/lib/api';

interface SecurityAlertsProps {
  data: ReportsResponse;
}

export function SecurityAlerts({ data }: SecurityAlertsProps) {
  const { failed_login_attempts, accounts_locked, accounts_suspended, otp_failures, top_failed_ips, recent_lockouts, recent_suspensions } = data.security_alerts;

  return (
    <PageSection
      icon={ShieldAlert}
      title="Security Alerts"
      description="Failed attempts, lockouts, and suspicious activity"
      contentClassName="space-y-6"
    >
        <div className="grid gap-4 grid-cols-4">
          <div className="rounded-lg border border-l-2 border-l-red-400 p-3">
            <p className="text-sm text-muted-foreground">Failed Logins</p>
            <p className="text-xl font-bold tabular-nums">{fmtNumber(failed_login_attempts)}</p>
          </div>
          <div className="rounded-lg border border-l-2 border-l-amber-400 p-3">
            <p className="text-sm text-muted-foreground">Accounts Locked</p>
            <p className="text-xl font-bold tabular-nums">{fmtNumber(accounts_locked)}</p>
          </div>
          <div className="rounded-lg border border-l-2 border-l-orange-400 p-3">
            <p className="text-sm text-muted-foreground">Accounts Suspended</p>
            <p className="text-xl font-bold tabular-nums">{fmtNumber(accounts_suspended)}</p>
          </div>
          <div className="rounded-lg border border-l-2 border-l-violet-400 p-3">
            <p className="text-sm text-muted-foreground">OTP Failures</p>
            <p className="text-xl font-bold tabular-nums">{fmtNumber(otp_failures)}</p>
          </div>
        </div>

        {top_failed_ips.length > 0 && (
          <div>
            <h4 className="mb-2 text-sm font-medium">Top Failed IPs</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>IP Address</TableHead>
                    <TableHead className="text-right">Failed Attempts</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {top_failed_ips.map((entry) => (
                    <TableRow key={entry.ip}>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip}</TableCell>
                      <TableCell className="text-right">{entry.count}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {recent_lockouts.length > 0 && (
          <div>
            <h4 className="mb-2 text-sm font-medium">Recent Lockouts</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>User</TableHead>
                    <TableHead>IP Address</TableHead>
                    <TableHead className="text-right">Locked At</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recent_lockouts.map((entry) => (
                    <TableRow key={`${entry.user_id}-${entry.locked_at}`}>
                      <TableCell>{entry.display_name}</TableCell>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip || '-'}</TableCell>
                      <TableCell className="text-right text-sm">
                        {formatDateTime(entry.locked_at)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {recent_suspensions.length > 0 && (
          <div>
            <h4 className="mb-2 text-sm font-medium">Recent Suspensions</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>User</TableHead>
                    <TableHead>IP Address</TableHead>
                    <TableHead className="text-right">Suspended At</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recent_suspensions.map((entry) => (
                    <TableRow key={`${entry.user_id}-${entry.suspended_at}`}>
                      <TableCell>{entry.display_name}</TableCell>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip || '-'}</TableCell>
                      <TableCell className="text-right text-sm">
                        {formatDateTime(entry.suspended_at)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {top_failed_ips.length === 0 && recent_lockouts.length === 0 && recent_suspensions.length === 0 && (
          <p className="text-sm text-muted-foreground">No security alerts for this period.</p>
        )}
    </PageSection>
  );
}
