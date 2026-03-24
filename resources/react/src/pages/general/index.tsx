import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { PageSection } from '@/components/ui/page-section';
import { Phone, Globe, Scale } from 'lucide-react';
import { SITE_PHONE_CHANNELS } from '@/lib/constants';
import type { AuthSettings, SitePhoneChannel } from '@/lib/api';

interface GeneralPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function GeneralPage({ settings, onUpdate }: GeneralPageProps) {
  const isTelegram = settings.site_phone_channel === 'telegram';

  return (
    <div className="space-y-4">
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

            <div className="flex items-start justify-between gap-4">
              <div>
                <label htmlFor="redirect_login" className="text-sm font-medium leading-snug">Redirect WordPress Login</label>
                <p className="text-sm text-muted-foreground">Redirect wp-login.php to your custom auth pages</p>
              </div>
              <Switch
                id="redirect_login"
                className="mt-0.5"
                checked={settings.redirect_login}
                onCheckedChange={(checked) => onUpdate('redirect_login', checked)}
                aria-label="Toggle redirect login"
              />
            </div>

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
    </div>
  );
}
