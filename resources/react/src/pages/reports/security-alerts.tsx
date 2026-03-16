import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
  const { failed_login_attempts, accounts_locked, otp_failures, top_failed_ips, recent_lockouts } = data.security_alerts;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <ShieldAlert className="h-4 w-4 text-muted-foreground" />
          Security Alerts
        </CardTitle>
        <CardDescription>Failed attempts, lockouts, and suspicious activity</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="grid gap-4 grid-cols-3">
          <div className="rounded-lg border p-3">
            <p className="text-sm text-muted-foreground">Failed Logins</p>
            <p className="text-xl font-bold">{fmtNumber(failed_login_attempts)}</p>
          </div>
          <div className="rounded-lg border p-3">
            <p className="text-sm text-muted-foreground">Accounts Locked</p>
            <p className="text-xl font-bold">{fmtNumber(accounts_locked)}</p>
          </div>
          <div className="rounded-lg border p-3">
            <p className="text-sm text-muted-foreground">OTP Failures</p>
            <p className="text-xl font-bold">{fmtNumber(otp_failures)}</p>
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
                      <TableCell className="font-mono text-sm">{entry.ip}</TableCell>
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
                      <TableCell className="font-mono text-sm">{entry.ip || '-'}</TableCell>
                      <TableCell className="text-right text-sm">
                        {new Date(entry.locked_at).toLocaleString()}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {top_failed_ips.length === 0 && recent_lockouts.length === 0 && (
          <p className="text-sm text-muted-foreground">No security alerts for this period.</p>
        )}
      </CardContent>
    </Card>
  );
}
