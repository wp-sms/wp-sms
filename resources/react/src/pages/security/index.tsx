import { MfaPolicies } from './mfa-policies';
import { RateLimiting } from './rate-limiting';
import { AccountCleanup } from './account-cleanup';
import { Captcha } from './captcha';
import { PhoneRestrictionNotice } from './phone-restriction-notice';
import type { AuthSettings } from '@/lib/api';

interface SecurityPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

export function SecurityPage({ section, settings, onUpdate, roles }: SecurityPageProps) {
  if (section === 'rate-limiting') {
    return <RateLimiting settings={settings} onUpdate={onUpdate} />;
  }

  if (section === 'account-cleanup') {
    return <AccountCleanup settings={settings} onUpdate={onUpdate} />;
  }

  if (section === 'captcha') {
    return <Captcha settings={settings} onUpdate={onUpdate} />;
  }

  if (section === 'phone-restriction') {
    return <PhoneRestrictionNotice />;
  }

  return <MfaPolicies settings={settings} onUpdate={onUpdate} roles={roles} />;
}
