import type { HealthCheck } from '@/lib/api';
import { HealthCheckRow } from './health-check-row';

interface AuthSectionProps {
  checks: HealthCheck[];
}

export function AuthSection({ checks }: AuthSectionProps) {
  return (
    <div className="space-y-1.5">
      {checks.map((check, i) => (
        <HealthCheckRow key={check.id} check={check} index={i} />
      ))}
    </div>
  );
}
