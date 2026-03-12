import { MfaFactors } from './mfa-factors';
import { Policies } from './policies';
import { RateLimiting } from './rate-limiting';
import type { AuthSettings } from '@/lib/api';

interface SecurityPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

export function SecurityPage({ section, settings, onUpdate, roles }: SecurityPageProps) {
  if (section === 'policies') {
    return <Policies settings={settings} onUpdate={onUpdate} roles={roles} />;
  }

  if (section === 'rate-limiting') {
    return <RateLimiting settings={settings} onUpdate={onUpdate} />;
  }

  return <MfaFactors settings={settings} onUpdate={onUpdate} />;
}
