import { __ } from '@wordpress/i18n';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription, SwitchField } from '@/components/ui/field';
import { PageSection } from '@/components/ui/page-section';
import { PageHeader } from '@/components/layout/page-header';
import { SlidersHorizontal, Phone, Globe, Scale, UserPlus, ScrollText, ShieldCheck } from 'lucide-react';
import { SITE_PHONE_CHANNELS, LOG_VERBOSITY } from '@/lib/constants';
import type { AuthSettings, SitePhoneChannel } from '@/lib/api';

interface GeneralPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  embedded?: boolean;
}

export function GeneralPage({ settings, onUpdate, embedded }: GeneralPageProps) {
  const isTelegram = settings.site_phone_channel === 'telegram';

  return (
    <div className="space-y-4">
      {!embedded && <PageHeader icon={SlidersHorizontal} title={__('General', 'wp-sms')} />}
      <PageSection
        icon={Globe}
        title={__('Auth Pages', 'wp-sms')}
        description={__('Configure authentication page URLs and login behavior', 'wp-sms')}
      >
          <div className="space-y-4">
            <div className="max-w-md">
              <Field>
                <FieldLabel htmlFor="auth_base_url">Base URL</FieldLabel>
                <Input
                  id="auth_base_url"
                  type="text"
                  value={settings.auth_base_url}
                  onChange={(e) => onUpdate('auth_base_url', e.target.value)}
                  placeholder="/auth"
                />
                <FieldDescription>
                  The base path for authentication pages (e.g., /auth, /login)
                </FieldDescription>
              </Field>
            </div>

            <SwitchField
              id="redirect_login"
              label="Redirect WordPress Login"
              description={__('Redirect wp-login.php to your custom auth pages', 'wp-sms')}
              checked={settings.redirect_login}
              onCheckedChange={(checked) => onUpdate('redirect_login', checked)}
            />

            {settings.redirect_login && (
              <p className="text-xs text-muted-foreground mt-3 rounded-md bg-muted/50 p-3">
                When enabled, visitors to <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.6875rem]">wp-login.php</code> will be redirected to <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.6875rem]">{settings.auth_base_url}/login</code>
              </p>
            )}
          </div>
      </PageSection>

      <PageSection
        icon={Phone}
        title={__('Admin Phone Number', 'wp-sms')}
        description={__('Site-level phone number used as a fallback for notifications and available as a template variable in flows.', 'wp-sms')}
      >
          <div className="space-y-4 max-w-md">
            <Field>
              <FieldLabel htmlFor="site_phone_channel">Channel</FieldLabel>
              <Select
                value={settings.site_phone_channel}
                onValueChange={(value) => onUpdate('site_phone_channel', value as SitePhoneChannel)}
              >
                <SelectTrigger id="site_phone_channel">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {SITE_PHONE_CHANNELS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel htmlFor="site_phone">
                {isTelegram ? 'Chat ID' : 'Phone Number'}
              </FieldLabel>
              <Input
                id="site_phone"
                type="text"
                value={settings.site_phone}
                onChange={(e) => onUpdate('site_phone', e.target.value)}
                placeholder={isTelegram ? '123456789' : '+1234567890'}
              />
              <FieldDescription>
                {'Used as {{site.phone}} in flow templates and as fallback for notifications when no recipients are configured.'}
              </FieldDescription>
            </Field>

            {isTelegram && (
              <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
                Telegram requires a configured Telegram bot in Authentication &rarr; Channels.
              </p>
            )}
          </div>
      </PageSection>

      <PageSection
        icon={Scale}
        title={__('Legal Links', 'wp-sms')}
        description={__('Terms of Service and Privacy Policy links shown on auth pages', 'wp-sms')}
      >
          <div className="space-y-4 max-w-md">
            <Field>
              <FieldLabel htmlFor="terms_url">Terms of Service URL</FieldLabel>
              <Input
                id="terms_url"
                type="url"
                value={settings.terms_url}
                onChange={(e) => onUpdate('terms_url', e.target.value)}
                placeholder="https://example.com/terms"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="privacy_url">Privacy Policy URL</FieldLabel>
              <Input
                id="privacy_url"
                type="url"
                value={settings.privacy_url}
                onChange={(e) => onUpdate('privacy_url', e.target.value)}
                placeholder="https://example.com/privacy"
              />
              <FieldDescription>
                When set, a consent line appears on the registration page.
              </FieldDescription>
            </Field>
          </div>
      </PageSection>

      <PageSection
        icon={ShieldCheck}
        title={__('Subscription Consent', 'wp-sms')}
        description={__('Default consent checkbox shown on subscription forms. Individual forms can override these settings. Leave consent text empty to disable.', 'wp-sms')}
      >
          <div className="space-y-4 max-w-md">
            <Field>
              <FieldLabel htmlFor="subscription_consent_text">Consent Text</FieldLabel>
              <Textarea
                id="subscription_consent_text"
                rows={3}
                value={settings.subscription_consent_text}
                onChange={(e) => onUpdate('subscription_consent_text', e.target.value)}
                placeholder='I agree to receive messages and accept the <a href="{privacy_url}">Privacy Policy</a>.'
              />
              <FieldDescription>
                HTML allowed. Use <code>{'{privacy_url}'}</code> as a placeholder for the privacy policy link.
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="subscription_consent_privacy_url">Privacy Policy URL</FieldLabel>
              <Input
                id="subscription_consent_privacy_url"
                value={settings.subscription_consent_privacy_url}
                onChange={(e) => onUpdate('subscription_consent_privacy_url', e.target.value)}
                placeholder={__('Leave empty to use WordPress privacy page', 'wp-sms')}
              />
            </Field>

            <SwitchField
              id="subscription_consent_required"
              label="Consent Required"
              description={__('Block submission if the consent checkbox is not checked', 'wp-sms')}
              checked={settings.subscription_consent_required}
              onCheckedChange={(checked) => onUpdate('subscription_consent_required', checked)}
            />
          </div>
      </PageSection>

      <PageSection
        icon={UserPlus}
        title={__('Auto-Create Accounts', 'wp-sms')}
        description={__('Account creation behavior when unrecognized users attempt to log in', 'wp-sms')}
      >
          <SwitchField
            id="auto_create_users"
            label="Auto-Create Accounts on Login"
            description={__('When someone logs in with a phone or email that doesn\'t have an account yet, automatically create one instead of rejecting them', 'wp-sms')}
            checked={settings.auto_create_users}
            onCheckedChange={(checked) => onUpdate('auto_create_users', checked)}
          />
      </PageSection>

      <PageSection
        icon={ScrollText}
        title={__('Log Settings', 'wp-sms')}
        description={__('Configure what gets logged and how long logs are retained', 'wp-sms')}
      >
          <div className="space-y-4 max-w-md">
            <Field>
              <FieldLabel htmlFor="log_verbosity">Verbosity</FieldLabel>
              <Select
                value={settings.log_verbosity}
                onValueChange={(value) => onUpdate('log_verbosity', value as AuthSettings['log_verbosity'])}
              >
                <SelectTrigger id="log_verbosity">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {LOG_VERBOSITY.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldDescription>
                {LOG_VERBOSITY.find((v) => v.value === settings.log_verbosity)?.description}
              </FieldDescription>
            </Field>
            <Field>
              <FieldLabel htmlFor="log_retention_days">Retention (days)</FieldLabel>
              <Input
                id="log_retention_days"
                type="number"
                min={1}
                max={365}
                value={settings.log_retention_days}
                onChange={(e) => onUpdate('log_retention_days', Number(e.target.value))}
              />
              <FieldDescription>How long to keep log entries before cleanup</FieldDescription>
            </Field>
          </div>
      </PageSection>
    </div>
  );
}
