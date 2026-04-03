import { __ } from '@wordpress/i18n';
import { Shield, Phone, Workflow, Info } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { getConfig, type OnboardingGoal } from '@/lib/api';

interface ConfigStepProps {
  goals: OnboardingGoal[];
  authEnabled: boolean;
  onAuthEnabledChange: (v: boolean) => void;
  sitePhone: string;
  onSitePhoneChange: (v: string) => void;
}

export function ConfigStep({ goals, authEnabled, onAuthEnabledChange, sitePhone, onSitePhoneChange }: ConfigStepProps) {
  const { detectedIntegrations } = getConfig();
  const hasAuth = goals.includes('auth');
  const hasCampaigns = goals.includes('campaigns') || goals.includes('notifications');

  return (
    <div className="space-y-4">
      {/* Header */}
      <div>
        <h1 className="text-xl font-extrabold tracking-tight">
          {__('Quick configuration', 'wp-sms')}
        </h1>
        <p className="text-sm text-muted-foreground mt-1.5 leading-relaxed">
          {__('Sensible defaults are set. Adjust anything you\'d like, or just continue.', 'wp-sms')}
        </p>
      </div>

      {/* Auth card */}
      {hasAuth && (
        <div className="border-2 rounded-md bg-card overflow-hidden">
          <div className="flex items-center gap-3 px-5 py-3.5 border-b">
            <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/[0.08] text-primary shrink-0">
              <Shield className="size-4" />
            </div>
            <span className="text-sm font-semibold">{__('Auth & Identity', 'wp-sms')}</span>
          </div>
          <div className="px-5 py-4">
            <div className="flex items-center justify-between gap-4">
              <div>
                <div className="text-sm font-medium">{__('Enable authentication', 'wp-sms')}</div>
                <div className="text-sm text-muted-foreground mt-0.5">
                  {__('Activates custom login, registration, MFA, and identity features.', 'wp-sms')}
                </div>
              </div>
              <Switch checked={authEnabled} onCheckedChange={onAuthEnabledChange} />
            </div>
          </div>
        </div>
      )}

      {/* Phone number card */}
      <div className="border-2 rounded-md bg-card overflow-hidden">
        <div className="flex items-center gap-3 px-5 py-3.5 border-b">
          <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-500/[0.08] text-blue-600 shrink-0">
            <Phone className="size-4" />
          </div>
          <div className="flex items-baseline gap-1.5">
            <span className="text-sm font-semibold">{__('Your phone number', 'wp-sms')}</span>
            <span className="text-sm text-muted-foreground">{'·'} {__('Optional', 'wp-sms')}</span>
          </div>
        </div>
        <div className="px-5 py-4">
          <p className="text-sm text-muted-foreground mb-3">
            {__('We\'ll use this for test messages and as your site\'s primary number.', 'wp-sms')}
          </p>
          <Input
            id="site_phone"
            type="tel"
            dir="ltr"
            className="max-w-xs"
            value={sitePhone}
            onChange={(e) => onSitePhoneChange(e.target.value)}
            placeholder="+1234567890"
          />
        </div>
      </div>

      {/* Campaigns & Automation card */}
      {hasCampaigns && (
        <div className="border-2 rounded-md bg-card overflow-hidden">
          <div className="flex items-center gap-3 px-5 py-3.5 border-b">
            <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/[0.08] text-primary shrink-0">
              <Workflow className="size-4" />
            </div>
            <span className="text-sm font-semibold">{__('Campaigns & Automation', 'wp-sms')}</span>
          </div>
          <div className="px-5 py-4 space-y-3">
            <p className="text-sm text-muted-foreground leading-relaxed">
              {__('Send messages when events happen and run bulk campaigns to your contacts.', 'wp-sms')}
            </p>
            <p className="text-sm text-muted-foreground leading-relaxed">
              {__('WSMS automatically handles STOP/START keywords for SMS compliance. Recipients can opt out by replying STOP.', 'wp-sms')}
            </p>
            <div className="flex items-start gap-2.5 p-3 rounded-md border text-sm text-muted-foreground">
              <Info className="size-4 shrink-0 mt-0.5" />
              <span>{__('After setup, head to Automation to create your first flow, or Audience to import contacts.', 'wp-sms')}</span>
            </div>
          </div>
        </div>
      )}

      {/* Detected integrations — only show if meaningful 3rd-party integrations */}
      {detectedIntegrations.length > 0 && (
        <div className="flex items-start gap-2.5 p-3 rounded-md border text-sm text-muted-foreground">
          <Info className="size-4 shrink-0 mt-0.5" />
          <span>
            {detectedIntegrations.map((i) => i.name).join(', ')}{' '}
            {__('detected — you can set up integrations in Channels after setup.', 'wp-sms')}
          </span>
        </div>
      )}
    </div>
  );
}
