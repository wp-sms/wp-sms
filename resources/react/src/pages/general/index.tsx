import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription, SwitchField } from '@/components/ui/field';
import { PageSection } from '@/components/ui/page-section';
import { PageHeader } from '@/components/layout/page-header';
import { SlidersHorizontal, Phone, Globe, Scale, UserPlus, ScrollText } from 'lucide-react';
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
      {!embedded && <PageHeader icon={SlidersHorizontal} title="General" />}
      <PageSection
        icon={Globe}
        title="Auth Pages"
        description="Configure authentication page URLs and login behavior"
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
              description="Redirect wp-login.php to your custom auth pages"
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
        title="Admin Phone Number"
        description="Site-level phone number used as a fallback for notifications and available as a template variable in flows."
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
        title="Legal Links"
        description="Terms of Service and Privacy Policy links shown on auth pages"
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
        icon={UserPlus}
        title="Auto-Create Accounts"
        description="Account creation behavior when unrecognized users attempt to log in"
      >
          <SwitchField
            id="auto_create_users"
            label="Auto-Create Accounts on Login"
            description="When someone logs in with a phone or email that doesn't have an account yet, automatically create one instead of rejecting them"
            checked={settings.auto_create_users}
            onCheckedChange={(checked) => onUpdate('auto_create_users', checked)}
          />
      </PageSection>

      <PageSection
        icon={ScrollText}
        title="Log Settings"
        description="Configure what gets logged and how long logs are retained"
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
