import { Card, CardContent } from '@/components/ui/card';
import { CheckCircle, UserPlus, ShieldCheck, AlertTriangle } from 'lucide-react';
import { fmtNumber } from '@/lib/utils';
import type { ReportsResponse } from '@/lib/api';

interface SummaryCardsProps {
  data: ReportsResponse;
}

const cards = [
  {
    key: 'success_rate' as const,
    label: 'Login Success Rate',
    icon: CheckCircle,
    getValue: (d: ReportsResponse) => `${d.auth_activity.login_success_rate}%`,
    getSub: (d: ReportsResponse) => `${fmtNumber(d.auth_activity.successful_logins)} of ${fmtNumber(d.auth_activity.total_logins)} logins`,
    color: 'text-emerald-600',
  },
  {
    key: 'registrations' as const,
    label: 'Registrations',
    icon: UserPlus,
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.total_registrations),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.auth_activity.password_resets)} password resets`,
    color: 'text-blue-600',
  },
  {
    key: 'mfa_adoption' as const,
    label: 'MFA Adoption',
    icon: ShieldCheck,
    getValue: (d: ReportsResponse) => `${d.user_security.mfa_adoption_rate}%`,
    getSub: (d: ReportsResponse) => `${fmtNumber(d.user_security.mfa_enrolled)} of ${fmtNumber(d.user_security.total_users)} users`,
    color: 'text-violet-600',
  },
  {
    key: 'failed_logins' as const,
    label: 'Failed Logins',
    icon: AlertTriangle,
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.failed_logins),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.security_alerts.accounts_locked)} locked, ${fmtNumber(d.security_alerts.accounts_suspended)} suspended`,
    color: 'text-amber-600',
  },
];

export function SummaryCards({ data }: SummaryCardsProps) {
  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map(({ key, label, icon: Icon, getValue, getSub, color }) => (
        <Card key={key}>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="text-2xl font-bold">{getValue(data)}</p>
                <p className="text-xs text-muted-foreground">{getSub(data)}</p>
              </div>
              <Icon className={`h-8 w-8 ${color} opacity-80`} />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
