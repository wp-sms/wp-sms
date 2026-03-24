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
    bgColor: 'bg-emerald-50',
    accent: 'border-t-emerald-500',
  },
  {
    key: 'registrations' as const,
    label: 'Registrations',
    icon: UserPlus,
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.total_registrations),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.auth_activity.password_resets)} password resets`,
    color: 'text-blue-600',
    bgColor: 'bg-blue-50',
    accent: 'border-t-blue-500',
  },
  {
    key: 'mfa_adoption' as const,
    label: 'MFA Adoption',
    icon: ShieldCheck,
    getValue: (d: ReportsResponse) => `${d.user_security.mfa_adoption_rate}%`,
    getSub: (d: ReportsResponse) => `${fmtNumber(d.user_security.mfa_enrolled)} of ${fmtNumber(d.user_security.total_users)} users`,
    color: 'text-violet-600',
    bgColor: 'bg-violet-50',
    accent: 'border-t-violet-500',
  },
  {
    key: 'failed_logins' as const,
    label: 'Failed Logins',
    icon: AlertTriangle,
    getValue: (d: ReportsResponse) => fmtNumber(d.auth_activity.failed_logins),
    getSub: (d: ReportsResponse) => `${fmtNumber(d.security_alerts.accounts_locked)} locked, ${fmtNumber(d.security_alerts.accounts_suspended)} suspended`,
    color: 'text-amber-600',
    bgColor: 'bg-amber-50',
    accent: 'border-t-amber-500',
  },
];

export function SummaryCards({ data }: SummaryCardsProps) {
  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map(({ key, label, icon: Icon, getValue, getSub, color, bgColor, accent }, i) => (
        <Card key={key} className={`border-t-2 ${accent}`} style={{ animationDelay: `${i * 60}ms` }}>
          <CardContent className="pt-6">
            <div className="flex items-start gap-3">
              <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${bgColor}`}>
                <Icon className={`h-5 w-5 ${color}`} />
              </div>
              <div className="space-y-1 min-w-0">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="text-3xl font-bold tracking-tight tabular-nums">{getValue(data)}</p>
                <p className="text-xs text-muted-foreground">{getSub(data)}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
