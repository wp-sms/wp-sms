import { __ } from '@wordpress/i18n';
import { PageSection } from '@/components/ui/page-section';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription, SwitchField } from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { ShieldAlert, CloudCog } from 'lucide-react';
import { toggleArrayItem } from '@/lib/constants';
import { cn } from '@/lib/utils';
import type { AuthSettings, CaptchaProvider, CaptchaAction, CaptchaSettings } from '@/lib/api';

interface CaptchaPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const PROVIDERS: { id: CaptchaProvider; label: string; description: string; comingSoon: boolean }[] = [
  { id: 'turnstile', label: 'Cloudflare Turnstile', description: __('Free, privacy-friendly, often invisible', 'wp-sms'), comingSoon: false },
  { id: 'recaptcha', label: 'Google reCAPTCHA', description: __('Widely used, v2 checkbox or v3 invisible', 'wp-sms'), comingSoon: true },
  { id: 'hcaptcha', label: 'hCaptcha', description: __('Privacy-focused alternative to reCAPTCHA', 'wp-sms'), comingSoon: true },
];

const ACTIONS: { id: CaptchaAction; label: string; description: string }[] = [
  { id: 'login', label: __('Login', 'wp-sms'), description: __('Password and passwordless login', 'wp-sms') },
  { id: 'register', label: __('Registration', 'wp-sms'), description: __('New account creation', 'wp-sms') },
  { id: 'forgot_password', label: __('Forgot Password', 'wp-sms'), description: __('Password reset requests', 'wp-sms') },
  { id: 'identify', label: __('Identify', 'wp-sms'), description: __('User lookup (not recommended — may break auto-login for returning users)', 'wp-sms') },
  { id: 'subscribe', label: __('Subscription Forms', 'wp-sms'), description: __('Newsletter form submissions', 'wp-sms') },
  { id: 'messaging_button', label: __('Messaging Button', 'wp-sms'), description: __('Contact messages from the messaging widget', 'wp-sms') },
];

export function Captcha({ settings, onUpdate }: CaptchaPageProps) {
  const captcha = settings.captcha;

  function update(patch: Partial<CaptchaSettings>) {
    onUpdate('captcha', { ...captcha, ...patch });
  }

  function toggleAction(action: CaptchaAction, enabled: boolean) {
    update({ protected_actions: toggleArrayItem(captcha.protected_actions, action, enabled) });
  }

  return (
    <div className="space-y-4">
      {/* Enable / Provider */}
      <PageSection
        icon={ShieldAlert}
        title={__('CAPTCHA Protection', 'wp-sms')}
        description={__('Protect authentication endpoints from bots and automated attacks', 'wp-sms')}
        active={captcha.enabled}
        actions={
          <Switch
            checked={captcha.enabled}
            onCheckedChange={(v) => update({ enabled: v })}
            aria-label={__('Toggle CAPTCHA', 'wp-sms')}
          />
        }
        contentClassName={captcha.enabled ? 'border-t pt-4 space-y-6' : undefined}
      >
        {captcha.enabled && (
          <>
            {/* Provider Selector */}
            <Field>
              <FieldLabel>{__('Provider', 'wp-sms')}</FieldLabel>
              <div className="grid gap-2">
                {PROVIDERS.map((p) => (
                  <label
                    key={p.id}
                    className={cn(
                      'flex items-center gap-3 rounded-md border p-3 transition-colors cursor-pointer',
                      captcha.provider === p.id ? 'border-primary bg-primary/5' : 'border-border',
                      p.comingSoon && 'cursor-not-allowed',
                    )}
                  >
                    <input
                      type="radio"
                      name="captcha-provider"
                      value={p.id}
                      checked={captcha.provider === p.id}
                      onChange={() => update({ provider: p.id })}
                      disabled={p.comingSoon}
                      className="sr-only"
                    />
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">{p.label}</span>
                        {p.comingSoon && <Badge variant="secondary" className="text-[10px] px-1.5 py-0">{__('Coming Soon', 'wp-sms')}</Badge>}
                      </div>
                      <p className="text-xs text-muted-foreground">{p.description}</p>
                    </div>
                    <div className={cn(
                      'h-4 w-4 rounded-full border-2 flex items-center justify-center',
                      captcha.provider === p.id ? 'border-primary' : 'border-muted-foreground/30',
                    )}>
                      {captcha.provider === p.id && <div className="h-2 w-2 rounded-full bg-primary" />}
                    </div>
                  </label>
                ))}
              </div>
            </Field>

            {/* API Keys */}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="captcha-site-key">{__('Site Key *', 'wp-sms')}</FieldLabel>
                <Input
                  id="captcha-site-key"
                  type="text"
                  value={captcha.site_key}
                  onChange={(e) => update({ site_key: e.target.value })}
                  placeholder={__('Enter site key', 'wp-sms')}
                />
                <FieldDescription>{__('Public key shown to visitors', 'wp-sms')}</FieldDescription>
              </Field>
              <Field>
                <FieldLabel htmlFor="captcha-secret-key">{__('Secret Key *', 'wp-sms')}</FieldLabel>
                <Input
                  id="captcha-secret-key"
                  type="password"
                  value={captcha.secret_key}
                  onChange={(e) => update({ secret_key: e.target.value })}
                  placeholder={__('Enter secret key', 'wp-sms')}
                />
                <FieldDescription>{__('Server-side key (never exposed to clients)', 'wp-sms')}</FieldDescription>
              </Field>
            </div>
          </>
        )}
      </PageSection>

      {/* Protected Actions */}
      {captcha.enabled && (
        <PageSection
          icon={CloudCog}
          title={__('Protected Actions', 'wp-sms')}
          description={__('Choose which actions require CAPTCHA verification', 'wp-sms')}
          contentClassName="border-t pt-4 space-y-4"
        >
            {ACTIONS.map((action) => (
              <label key={action.id} className="flex items-start gap-3">
                <Checkbox
                  checked={captcha.protected_actions.includes(action.id)}
                  onCheckedChange={(v) => toggleAction(action.id, !!v)}
                  className="mt-0.5"
                />
                <div>
                  <span className="text-sm font-medium">{action.label}</span>
                  <p className="text-xs text-muted-foreground">{action.description}</p>
                </div>
              </label>
            ))}

            <SwitchField
              id="captcha-fail-open"
              label={__('Fail Open', 'wp-sms')}
              description={captcha.fail_open
                ? __('If the CAPTCHA service is unreachable, users will be allowed through. Less secure but avoids lockouts.', 'wp-sms')
                : __('If the CAPTCHA service is unreachable, users will be blocked. More secure but may cause lockouts during outages.', 'wp-sms')}
              checked={captcha.fail_open}
              onCheckedChange={(v) => update({ fail_open: v })}
              className="border-t pt-4 mt-4"
            />
        </PageSection>
      )}
    </div>
  );
}
