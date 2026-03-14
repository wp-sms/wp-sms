import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ChannelRow } from '@/components/channel-row';
import { ChannelSettingsSheet } from '@/components/channel-settings-sheet';
import { SocialSettingsSheet } from '@/components/social-settings-sheet';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription } from '@/components/ui/drawer';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import type { ChannelId } from '@/lib/constants';
import { GoogleIcon, TelegramIcon, AppleIcon, LinkedInIcon, FacebookIcon, MicrosoftIcon, GitHubIcon, TwitterIcon } from '@/components/icons/social';
import { SOCIAL_METHODS } from '@/lib/constants';
import { Smartphone, Mail, KeyRound, Fingerprint, Send, ExternalLink } from 'lucide-react';
import type { AuthSettings, PhoneChannelSettings, EmailChannelSettings, TelegramSettings } from '@/lib/api';

interface ChannelsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const SOCIAL_ICONS: Record<string, React.ComponentType<React.SVGProps<SVGSVGElement>>> = {
  google: GoogleIcon,
  telegram: TelegramIcon,
  apple: AppleIcon,
  facebook: FacebookIcon,
  microsoft: MicrosoftIcon,
  github: GitHubIcon,
  linkedin: LinkedInIcon,
  twitter: TwitterIcon,
};

function getChannelSummary(channelId: 'phone' | 'email', settings: PhoneChannelSettings | EmailChannelSettings): string {
  const parts: string[] = [];

  const methods = settings.verification_methods ?? ['otp'];
  if (methods.includes('otp') && methods.includes('magic_link')) {
    parts.push('OTP + Magic Link');
  } else if (methods.includes('magic_link')) {
    parts.push('Magic Link');
  } else {
    parts.push('OTP');
  }

  if (settings.code_length && methods.includes('otp')) {
    parts.push(`${settings.code_length}-digit`);
  }

  if (channelId === 'phone') {
    const phone = settings as PhoneChannelSettings;
    if (phone.delivery_channel) {
      parts.push(`via ${phone.delivery_channel.toUpperCase()}`);
    }
  }

  return parts.join(' · ');
}

function getTelegramMfaSummary(tg: TelegramSettings): string {
  if (!tg.enabled) {
    return 'Send verification codes via Telegram bot message';
  }
  if (tg.bot_username) {
    return `@${tg.bot_username} · ${tg.code_length ?? 6}-digit OTP`;
  }
  return 'OTP via Telegram bot';
}

export function Channels({ settings, onUpdate }: ChannelsProps) {
  const [editingChannel, setEditingChannel] = useState<ChannelId | null>(null);
  const [editingSocial, setEditingSocial] = useState<string | null>(null);
  const [editingTelegramMfa, setEditingTelegramMfa] = useState(false);
  const socialSettings = settings.social ?? {};
  const telegramSettings = settings.telegram ?? {} as TelegramSettings;

  function updatePhone(partial: Partial<PhoneChannelSettings>) {
    onUpdate('phone', { ...settings.phone, ...partial });
  }

  function updateEmail(partial: Partial<EmailChannelSettings>) {
    onUpdate('email', { ...settings.email, ...partial });
  }

  function updateTelegram(partial: Partial<TelegramSettings>) {
    onUpdate('telegram', { ...telegramSettings, ...partial });
  }

  const phoneUsage = settings.phone.usage ?? 'login';
  const emailUsage = settings.email.usage ?? 'login';

  // Determine cross-reference states
  const phoneInLogin = settings.phone.enabled && phoneUsage === 'login';
  const phoneInMfa = settings.phone.enabled && phoneUsage === 'mfa';
  const emailInLogin = settings.email.enabled && emailUsage === 'login';
  const emailInMfa = settings.email.enabled && emailUsage === 'mfa';

  return (
    <>
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Left Column — Sign-in Methods */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Sign-in Methods</CardTitle>
              <CardDescription>Configure how users sign in to your application</CardDescription>
            </CardHeader>
            <CardContent className="space-y-0">
              {/* Phone */}
              <ChannelRow
                icon={Smartphone}
                title="Phone"
                description={phoneInLogin ? getChannelSummary('phone', settings.phone) : undefined}
                enabled={phoneInLogin}
                onToggle={(v) => {
                  if (v) {
                    updatePhone({ enabled: true, usage: 'login' });
                  } else {
                    updatePhone({ enabled: false });
                  }
                }}
                onConfigure={() => setEditingChannel('phone')}
                crossRefLabel={phoneInMfa ? 'Currently used for MFA' : undefined}
                onCrossRefAction={phoneInMfa ? () => updatePhone({ usage: 'login' }) : undefined}
                crossRefActionLabel={phoneInMfa ? 'Move to sign-in' : undefined}
              />

              <Separator />

              {/* Email */}
              <ChannelRow
                icon={Mail}
                title="Email"
                description={emailInLogin ? getChannelSummary('email', settings.email) : undefined}
                enabled={emailInLogin}
                onToggle={(v) => {
                  if (v) {
                    updateEmail({ enabled: true, usage: 'login' });
                  } else {
                    updateEmail({ enabled: false });
                  }
                }}
                onConfigure={() => setEditingChannel('email')}
                crossRefLabel={emailInMfa ? 'Currently used for MFA' : undefined}
                onCrossRefAction={emailInMfa ? () => updateEmail({ usage: 'login' }) : undefined}
                crossRefActionLabel={emailInMfa ? 'Move to sign-in' : undefined}
              />

              <Separator />

              {/* Password */}
              <ChannelRow
                icon={KeyRound}
                title="Password"
                description="Traditional username & password"
                enabled={settings.password.enabled}
                onToggle={(v) => onUpdate('password', { ...settings.password, enabled: v })}
                onConfigure={() => setEditingChannel('password')}
              />
            </CardContent>
          </Card>

          {/* Social Connections */}
          <Card>
            <CardHeader>
              <CardTitle>Social Connections</CardTitle>
              <CardDescription>Allow users to sign in with social accounts</CardDescription>
            </CardHeader>
            <CardContent className="space-y-0">
              {SOCIAL_METHODS.map((method, i) => {
                const providerSettings = socialSettings[method.id] ?? {};
                const isEnabled = !method.comingSoon && !!providerSettings.enabled;

                return (
                  <div key={method.id}>
                    {i > 0 && <Separator />}
                    <ChannelRow
                      icon={SOCIAL_ICONS[method.id]}
                      title={method.label}
                      comingSoon={method.comingSoon}
                      enabled={isEnabled}
                      onToggle={method.comingSoon ? undefined : (v) => {
                        const social = { ...socialSettings };
                        social[method.id] = { ...providerSettings, enabled: v };
                        onUpdate('social', social);
                      }}
                      onConfigure={method.comingSoon ? undefined : () => setEditingSocial(method.id)}
                      description={isEnabled && providerSettings.client_id ? 'Configured' : undefined}
                    />
                  </div>
                );
              })}
            </CardContent>

            {/* Profile Data Sync */}
            <div className="border-t px-6 py-4">
              <div className="space-y-2">
                <Label className="text-sm font-medium">Profile Data Sync</Label>
                <RadioGroup
                  value={settings.social_profile_sync ?? 'registration_only'}
                  onValueChange={(v) => {
                    if (v === 'registration_only' || v === 'every_login') {
                      onUpdate('social_profile_sync', v);
                    }
                  }}
                  className="space-y-1"
                >
                  <label className="flex items-center gap-2 cursor-pointer">
                    <RadioGroupItem value="registration_only" />
                    <span className="text-sm">Only at registration</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <RadioGroupItem value="every_login" />
                    <span className="text-sm">Every login</span>
                  </label>
                </RadioGroup>
              </div>
            </div>
          </Card>
        </div>

        {/* Right Column — Multi-Factor Authentication */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Multi-Factor Authentication</CardTitle>
              <CardDescription>Add an extra layer of security</CardDescription>
            </CardHeader>
            <CardContent className="space-y-0">
              {/* Phone MFA */}
              <ChannelRow
                icon={Smartphone}
                title="Phone MFA"
                description={phoneInMfa ? getChannelSummary('phone', settings.phone) : undefined}
                enabled={phoneInMfa}
                onToggle={(v) => {
                  if (v) {
                    updatePhone({ enabled: true, usage: 'mfa' });
                  } else {
                    updatePhone({ enabled: false });
                  }
                }}
                onConfigure={() => setEditingChannel('phone')}
                crossRefLabel={phoneInLogin ? 'Currently used for sign-in' : undefined}
                onCrossRefAction={phoneInLogin ? () => updatePhone({ usage: 'mfa' }) : undefined}
                crossRefActionLabel={phoneInLogin ? 'Move to MFA' : undefined}
              />

              <Separator />

              {/* Email MFA */}
              <ChannelRow
                icon={Mail}
                title="Email MFA"
                description={emailInMfa ? getChannelSummary('email', settings.email) : undefined}
                enabled={emailInMfa}
                onToggle={(v) => {
                  if (v) {
                    updateEmail({ enabled: true, usage: 'mfa' });
                  } else {
                    updateEmail({ enabled: false });
                  }
                }}
                onConfigure={() => setEditingChannel('email')}
                crossRefLabel={emailInLogin ? 'Currently used for sign-in' : undefined}
                onCrossRefAction={emailInLogin ? () => updateEmail({ usage: 'mfa' }) : undefined}
                crossRefActionLabel={emailInLogin ? 'Move to MFA' : undefined}
              />

              <Separator />

              {/* Telegram MFA */}
              <ChannelRow
                icon={Send}
                title="Telegram MFA"
                description={getTelegramMfaSummary(telegramSettings)}
                enabled={!!telegramSettings.enabled}
                onToggle={(v) => updateTelegram({ enabled: v })}
                onConfigure={() => setEditingTelegramMfa(true)}
              />

              <Separator />

              {/* TOTP (Authenticator App) */}
              <ChannelRow
                icon={KeyRound}
                title="Authenticator App"
                description={settings.totp.enabled ? 'TOTP — Google Authenticator, Authy, 1Password' : undefined}
                enabled={settings.totp.enabled}
                onToggle={(v) => onUpdate('totp', { ...settings.totp, enabled: v })}
              />

              <Separator />

              {/* Biometric */}
              <ChannelRow
                icon={Fingerprint}
                title="Biometric"
                comingSoon
              />
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Social Settings Sheet */}
      {editingSocial && (
        <SocialSettingsSheet
          open={editingSocial !== null}
          onOpenChange={(open) => { if (!open) setEditingSocial(null); }}
          providerId={editingSocial}
          providerLabel={SOCIAL_METHODS.find((m) => m.id === editingSocial)?.label ?? editingSocial}
          icon={SOCIAL_ICONS[editingSocial] ?? GoogleIcon}
          settings={socialSettings[editingSocial] ?? {}}
          onUpdate={(partial) => {
            const social = { ...socialSettings };
            social[editingSocial] = { ...(social[editingSocial] ?? {}), ...partial };
            onUpdate('social', social);
          }}
        />
      )}

      {/* Telegram MFA Settings Drawer */}
      <Drawer open={editingTelegramMfa} onOpenChange={setEditingTelegramMfa} direction="right">
        <DrawerContent className="sm:max-w-md overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
                <Send className="h-4 w-4 text-muted-foreground" />
              </div>
              Telegram MFA Settings
            </DrawerTitle>
            <DrawerDescription>
              Send MFA verification codes to users via a Telegram bot. Users link their account by starting a conversation with your bot.
            </DrawerDescription>
          </DrawerHeader>

          <div className="space-y-6 px-4 pb-4">
            <a
              href="https://t.me/botfather"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-2 text-sm text-primary hover:underline"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              Create a bot with @BotFather
            </a>

            <Field>
              <FieldLabel htmlFor="tg-bot-token">Bot Token</FieldLabel>
              <Input
                id="tg-bot-token"
                type="password"
                value={telegramSettings.bot_token ?? ''}
                onChange={(e) => updateTelegram({ bot_token: e.target.value })}
                placeholder="123456789:ABCdefGhIjKlmNoPqRsTuVwXyZ"
              />
              <FieldDescription>
                The API token from @BotFather. Go to BotFather {'>'} select your bot {'>'} API Token.
              </FieldDescription>
            </Field>

            {telegramSettings.bot_username && (
              <div className="rounded-md border bg-muted/50 p-3">
                <div className="text-xs font-medium text-muted-foreground">Connected Bot</div>
                <div className="text-sm font-medium mt-1">@{telegramSettings.bot_username}</div>
              </div>
            )}

            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">How it works</div>
              <ul className="list-disc list-inside space-y-1">
                <li className="text-xs text-muted-foreground">Users who sign in with Telegram Login are auto-enrolled for Telegram MFA</li>
                <li className="text-xs text-muted-foreground">Other users can link their Telegram account by clicking a deep link to your bot</li>
                <li className="text-xs text-muted-foreground">During MFA, a verification code is sent as a bot message in Telegram</li>
              </ul>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="tg-code-length">Code Length</FieldLabel>
              <div className="flex gap-2">
                {[4, 6].map((len) => (
                  <button
                    key={len}
                    type="button"
                    onClick={() => updateTelegram({ code_length: len })}
                    className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${(telegramSettings.code_length ?? 6) === len ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-accent'}`}
                  >
                    {len} digits
                  </button>
                ))}
              </div>
            </Field>

            <Field>
              <FieldLabel htmlFor="tg-expiry">Code Expiry (seconds)</FieldLabel>
              <Input
                id="tg-expiry"
                type="number"
                min={60}
                max={900}
                value={telegramSettings.expiry ?? 300}
                onChange={(e) => updateTelegram({ expiry: parseInt(e.target.value) || 300 })}
              />
              <FieldDescription>
                How long a verification code is valid. Default: 300 seconds (5 minutes).
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="tg-cooldown">Cooldown (seconds)</FieldLabel>
              <Input
                id="tg-cooldown"
                type="number"
                min={10}
                max={300}
                value={telegramSettings.cooldown ?? 60}
                onChange={(e) => updateTelegram({ cooldown: parseInt(e.target.value) || 60 })}
              />
              <FieldDescription>
                Minimum wait time between code requests. Prevents abuse.
              </FieldDescription>
            </Field>
          </div>
        </DrawerContent>
      </Drawer>

      {/* Settings Sheet */}
      <ChannelSettingsSheet
        open={editingChannel !== null}
        onOpenChange={(open) => { if (!open) setEditingChannel(null); }}
        channelId={editingChannel ?? 'phone'}
        settings={
          editingChannel === 'email'
            ? settings.email
            : editingChannel === 'password'
              ? settings.password
              : settings.phone
        }
        onUpdate={(partial) => {
          if (editingChannel === 'email') {
            updateEmail(partial);
          } else if (editingChannel === 'password') {
            onUpdate('password', { ...settings.password, ...partial });
          } else {
            updatePhone(partial);
          }
        }}
      />
    </>
  );
}
