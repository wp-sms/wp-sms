import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/layout/page-header';
import { Shield } from 'lucide-react';
import { MfaPolicies } from './mfa-policies';
import { RateLimiting } from './rate-limiting';
import { AccountCleanup } from './account-cleanup';
import { Captcha } from './captcha';
import { PhoneRestriction } from '@/pages/messaging/phone-restriction';
import type { AuthSettings } from '@/lib/api';

const TABS = ['mfa-policies', 'captcha', 'rate-limiting', 'phone-restrictions', 'account-cleanup'] as const;

interface SecurityPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
  embedded?: boolean;
}

export function SecurityPage({ settings, onUpdate, roles, embedded }: SecurityPageProps) {
  const [activeTab, setActiveTab] = useState<string>(TABS[0]);

  const tabsList = (
    <TabsList variant="line" className={embedded ? '' : 'mt-3'}>
      <TabsTrigger value="mfa-policies">{__('MFA Policies', 'wp-sms')}</TabsTrigger>
      <TabsTrigger value="captcha">{__('CAPTCHA', 'wp-sms')}</TabsTrigger>
      <TabsTrigger value="rate-limiting">{__('Rate Limiting', 'wp-sms')}</TabsTrigger>
      <TabsTrigger value="phone-restrictions">{__('Phone Restrictions', 'wp-sms')}</TabsTrigger>
      <TabsTrigger value="account-cleanup">{__('Account Cleanup', 'wp-sms')}</TabsTrigger>
    </TabsList>
  );

  return (
    <Tabs value={activeTab} onValueChange={setActiveTab}>
      {embedded ? tabsList : (
        <PageHeader icon={Shield} title={__('Security', 'wp-sms')}>
          {tabsList}
        </PageHeader>
      )}

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
