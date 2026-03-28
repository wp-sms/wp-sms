import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/layout/page-header';
import { Shield } from 'lucide-react';
import { useSubTabs } from '@/hooks/use-sub-tabs';
import { MfaPolicies } from './mfa-policies';
import { RateLimiting } from './rate-limiting';
import { AccountCleanup } from './account-cleanup';
import { Captcha } from './captcha';
import { PhoneRestriction } from '@/pages/messaging/phone-restriction';
import type { AuthSettings } from '@/lib/api';

const TABS = ['mfa-policies', 'captcha', 'rate-limiting', 'phone-restrictions', 'account-cleanup'] as const;

interface SecurityPageProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

export function SecurityPage({ subTab, onNavigate, settings, onUpdate, roles }: SecurityPageProps) {
  const [activeTab, handleTabChange] = useSubTabs('s-security', TABS, subTab, onNavigate);

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader icon={Shield} title="Security">
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="mfa-policies">MFA Policies</TabsTrigger>
          <TabsTrigger value="captcha">CAPTCHA</TabsTrigger>
          <TabsTrigger value="rate-limiting">Rate Limiting</TabsTrigger>
          <TabsTrigger value="phone-restrictions">Phone Restrictions</TabsTrigger>
          <TabsTrigger value="account-cleanup">Account Cleanup</TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="mfa-policies">
        <MfaPolicies settings={settings} onUpdate={onUpdate} roles={roles} />
      </TabsContent>

      <TabsContent value="captcha">
        <Captcha settings={settings} onUpdate={onUpdate} />
      </TabsContent>

      <TabsContent value="rate-limiting">
        <RateLimiting settings={settings} onUpdate={onUpdate} />
      </TabsContent>

      <TabsContent value="phone-restrictions">
        {activeTab === 'phone-restrictions' && <PhoneRestriction embedded />}
      </TabsContent>

      <TabsContent value="account-cleanup">
        <AccountCleanup settings={settings} onUpdate={onUpdate} />
      </TabsContent>
    </Tabs>
  );
}
