import { __ } from '@wordpress/i18n';
import { Card, CardContent } from '@/components/ui/card';
import { fmtNumber } from '@/lib/utils';
import type { ReportsResponse } from '@/lib/api';

interface SummaryCardsProps {
  data: ReportsResponse;
}

const cards = [
  {
    key: 'success_rate' as const,
    label: __('Login Success Rate', 'wp-sms'),
    getValue: (d: ReportsResponse) => `${d.auth_activity.login_success_rate}%`,
    getSub: (d: ReportsResponse) => `${fmtNumber(d.auth_activity.successful_logins)} of ${fmtNumber(d.auth_activity.total_logins)} logins`,
  },
  {
    key: 'registrations' as const,
    label: __('Registrations', 'wp-sms'),
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.total_registrations),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.auth_activity.password_resets)} password resets`,
  },
  {
    key: 'mfa_adoption' as const,
    label: __('MFA Adoption', 'wp-sms'),
    getValue: (d: ReportsResponse) => `${d.user_security.mfa_adoption_rate}%`,
    getSub: (d: ReportsResponse) => `${fmtNumber(d.user_security.mfa_enrolled)} of ${fmtNumber(d.user_security.total_users)} users`,
  },
  {
    key: 'failed_logins' as const,
    label: __('Failed Logins', 'wp-sms'),
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.failed_logins),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.security_alerts.accounts_locked)} locked, ${fmtNumber(d.security_alerts.accounts_suspended)} suspended`,
  },
];

export function SummaryCards({ data }: SummaryCardsProps) {
  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map(({ key, label, getValue, getSub }, i) => (
        <Card key={key} style={{ animationDelay: `${i * 60}ms` }}>
          <CardContent className="pt-6">
            <div className="space-y-1">
              <p className="text-3xl font-bold tracking-tight tabular-nums">{getValue(data)}</p>
              <p className="text-sm text-muted-foreground">{label}</p>
              <p className="text-xs text-muted-foreground">{getSub(data)}</p>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
