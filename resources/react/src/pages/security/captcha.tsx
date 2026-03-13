import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { ShieldAlert, CloudCog } from 'lucide-react';
import { toggleArrayItem } from '@/lib/constants';
import type { AuthSettings, CaptchaProvider, CaptchaAction, CaptchaSettings } from '@/lib/api';

interface CaptchaPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const PROVIDERS: { id: CaptchaProvider; label: string; description: string; comingSoon: boolean }[] = [
  { id: 'turnstile', label: 'Cloudflare Turnstile', description: 'Free, privacy-friendly, often invisible', comingSoon: false },
  { id: 'recaptcha', label: 'Google reCAPTCHA', description: 'Widely used, v2 checkbox or v3 invisible', comingSoon: true },
  { id: 'hcaptcha', label: 'hCaptcha', description: 'Privacy-focused alternative to reCAPTCHA', comingSoon: true },
];

const ACTIONS: { id: CaptchaAction; label: string; description: string }[] = [
  { id: 'login', label: 'Login', description: 'Password and passwordless login' },
  { id: 'register', label: 'Registration', description: 'New account creation' },
  { id: 'forgot_password', label: 'Forgot Password', description: 'Password reset requests' },
  { id: 'identify', label: 'Identify', description: 'User lookup (not recommended — may break auto-login for returning users)' },
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
      <Card className={captcha.enabled ? 'border-l-2 border-l-primary' : 'opacity-60'}>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <div className="space-y-1">
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldAlert className="h-4 w-4 text-muted-foreground" />
              CAPTCHA Protection
            </CardTitle>
            <CardDescription>Protect authentication endpoints from bots and automated attacks</CardDescription>
          </div>
          <Switch
            checked={captcha.enabled}
            onCheckedChange={(v) => update({ enabled: v })}
            aria-label="Toggle CAPTCHA"
          />
        </CardHeader>
        {captcha.enabled && (
          <CardContent className="border-t pt-4 space-y-6">
            {/* Provider Selector */}
            <Field>
              <FieldLabel>Provider</FieldLabel>
              <div className="grid gap-2">
                {PROVIDERS.map((p) => (
                  <label
                    key={p.id}
                    className={`flex items-center gap-3 rounded-md border p-3 transition-colors ${
                      captcha.provider === p.id ? 'border-primary bg-primary/5 cursor-pointer' : 'border-border cursor-pointer'
                    } ${p.comingSoon ? 'opacity-50 cursor-not-allowed' : ''}`}
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
                        {p.comingSoon && <Badge variant="outline">Coming Soon</Badge>}
                      </div>
                      <p className="text-xs text-muted-foreground">{p.description}</p>
                    </div>
                    <div className={`h-4 w-4 rounded-full border-2 flex items-center justify-center ${
                      captcha.provider === p.id ? 'border-primary' : 'border-muted-foreground/30'
                    }`}>
                      {captcha.provider === p.id && <div className="h-2 w-2 rounded-full bg-primary" />}
                    </div>
                  </label>
                ))}
              </div>
            </Field>

            {/* API Keys */}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="captcha-site-key">Site Key</FieldLabel>
                <Input
                  id="captcha-site-key"
                  type="text"
                  value={captcha.site_key}
                  onChange={(e) => update({ site_key: e.target.value })}
                  placeholder="Enter site key"
                />
                <FieldDescription>Public key shown to visitors</FieldDescription>
              </Field>
              <Field>
                <FieldLabel htmlFor="captcha-secret-key">Secret Key</FieldLabel>
                <Input
                  id="captcha-secret-key"
                  type="password"
                  value={captcha.secret_key}
                  onChange={(e) => update({ secret_key: e.target.value })}
                  placeholder="Enter secret key"
                />
                <FieldDescription>Server-side key (never exposed to clients)</FieldDescription>
              </Field>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Protected Actions */}
      {captcha.enabled && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <CloudCog className="h-4 w-4 text-muted-foreground" />
              Protected Actions
            </CardTitle>
            <CardDescription>Choose which actions require CAPTCHA verification</CardDescription>
          </CardHeader>
          <CardContent className="border-t pt-4 space-y-4">
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

            {/* Fail behavior */}
            <div className="border-t pt-4 mt-4">
              <label className="flex items-start gap-3">
                <Switch
                  checked={captcha.fail_open}
                  onCheckedChange={(v) => update({ fail_open: v })}
                />
                <div>
                  <span className="text-sm font-medium">Fail Open</span>
                  <p className="text-xs text-muted-foreground">
                    {captcha.fail_open
                      ? 'If the CAPTCHA service is unreachable, users will be allowed through. Less secure but avoids lockouts.'
                      : 'If the CAPTCHA service is unreachable, users will be blocked. More secure but may cause lockouts during outages.'}
                  </p>
                </div>
              </label>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
