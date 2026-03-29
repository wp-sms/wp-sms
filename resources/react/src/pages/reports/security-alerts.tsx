import { __ } from '@wordpress/i18n';
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
import { formatCountry } from '@/lib/country';
import type { ReportsResponse } from '@/lib/api';

interface SecurityAlertsProps {
  data: ReportsResponse;
}

export function SecurityAlerts({ data }: SecurityAlertsProps) {
  const { failed_login_attempts, accounts_locked, accounts_suspended, otp_failures, top_failed_ips, recent_lockouts, recent_suspensions } = data.security_alerts;

  return (
    <PageSection
      icon={ShieldAlert}
      title={__('Security Alerts', 'wp-sms')}
      description={__('Failed attempts, lockouts, and suspicious activity', 'wp-sms')}
      contentClassName="space-y-6"
    >
        <div className="flex items-baseline gap-x-4 gap-y-1 flex-wrap text-sm text-muted-foreground">
          <span><strong className="font-bold tabular-nums text-foreground">{fmtNumber(failed_login_attempts)}</strong> {__('failed logins', 'wp-sms')}</span>
          <span aria-hidden>·</span>
          <span><strong className="font-bold tabular-nums text-foreground">{fmtNumber(accounts_locked)}</strong> {__('accounts locked', 'wp-sms')}</span>
          <span aria-hidden>·</span>
          <span><strong className="font-bold tabular-nums text-foreground">{fmtNumber(accounts_suspended)}</strong> {__('accounts suspended', 'wp-sms')}</span>
          <span aria-hidden>·</span>
          <span><strong className="font-bold tabular-nums text-foreground">{fmtNumber(otp_failures)}</strong> {__('OTP failures', 'wp-sms')}</span>
        </div>

        {top_failed_ips.length > 0 && (
          <div>
            <h4 className="mb-2 text-sm font-medium">{__('Top Failed IPs', 'wp-sms')}</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{__('IP Address', 'wp-sms')}</TableHead>
                    <TableHead>{__('Country', 'wp-sms')}</TableHead>
                    <TableHead className="text-end">{__('Failed Attempts', 'wp-sms')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {top_failed_ips.map((entry) => (
                    <TableRow key={entry.ip}>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip}</TableCell>
                      <TableCell className="text-sm">{formatCountry(entry.country)}</TableCell>
                      <TableCell className="text-end">{entry.count}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {recent_lockouts.length > 0 && (
          <div>
            <h4 className="mb-2 text-sm font-medium">{__('Recent Lockouts', 'wp-sms')}</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{__('User', 'wp-sms')}</TableHead>
                    <TableHead>{__('IP Address', 'wp-sms')}</TableHead>
                    <TableHead>{__('Country', 'wp-sms')}</TableHead>
                    <TableHead className="text-end">{__('Locked At', 'wp-sms')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recent_lockouts.map((entry) => (
                    <TableRow key={`${entry.user_id}-${entry.locked_at}`}>
                      <TableCell>{entry.display_name}</TableCell>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip || '-'}</TableCell>
                      <TableCell className="text-sm">{formatCountry(entry.country)}</TableCell>
                      <TableCell className="text-end text-sm">
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
            <h4 className="mb-2 text-sm font-medium">{__('Recent Suspensions', 'wp-sms')}</h4>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{__('User', 'wp-sms')}</TableHead>
                    <TableHead>{__('IP Address', 'wp-sms')}</TableHead>
                    <TableHead>{__('Country', 'wp-sms')}</TableHead>
                    <TableHead className="text-end">{__('Suspended At', 'wp-sms')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recent_suspensions.map((entry) => (
                    <TableRow key={`${entry.user_id}-${entry.suspended_at}`}>
                      <TableCell>{entry.display_name}</TableCell>
                      <TableCell className="font-mono text-xs tracking-tight">{entry.ip || '-'}</TableCell>
                      <TableCell className="text-sm">{formatCountry(entry.country)}</TableCell>
                      <TableCell className="text-end text-sm">
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
          <p className="text-sm text-muted-foreground">{__('No security alerts for this period.', 'wp-sms')}</p>
        )}
    </PageSection>
  );
}
