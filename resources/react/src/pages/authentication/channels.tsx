import { __, sprintf } from '@wordpress/i18n';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ChannelRow } from '@/components/channel-row';
import { ChannelSettingsPanel } from '@/components/channel-settings-panel';
import { SocialSettingsPanel } from '@/components/social-settings-panel';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription } from '@/components/ui/drawer';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import type { ChannelId } from '@/lib/constants';
import { GoogleIcon, TelegramIcon, LineIcon, AppleIcon, LinkedInIcon, FacebookIcon, MicrosoftIcon, GitHubIcon, TwitterIcon } from '@/components/icons/social';
import { SOCIAL_METHODS } from '@/lib/constants';
import { Switch } from '@/components/ui/switch';
import { Smartphone, Mail, KeyRound, Fingerprint, Send, MessageCircle, ExternalLink, LogIn, Shield } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import type { AuthSettings, PhoneChannelSettings, EmailChannelSettings, TelegramSettings, LineSettings } from '@/lib/api';

interface ChannelsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const SOCIAL_ICONS: Record<string, React.ComponentType<React.SVGProps<SVGSVGElement>>> = {
  google: GoogleIcon,
  telegram: TelegramIcon,
  line: LineIcon,
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
    parts.push(__('OTP + Magic Link', 'wp-sms'));
  } else if (methods.includes('magic_link')) {
    parts.push(__('Magic Link', 'wp-sms'));
  } else {
    parts.push(__('OTP', 'wp-sms'));
  }

  if (settings.code_length && methods.includes('otp')) {
    parts.push(sprintf(__('%d-digit', 'wp-sms'), settings.code_length));
  }

  if (channelId === 'phone') {
    const phone = settings as PhoneChannelSettings;
    if (phone.delivery_channel) {
      parts.push(sprintf(__('via %s', 'wp-sms'), phone.delivery_channel.toUpperCase()));
    }
  }

  return parts.join(' · ');
}

function getTelegramMfaSummary(tg: TelegramSettings): string {
  if (!tg.enabled) {
    return __('Send verification codes via Telegram bot message', 'wp-sms');
  }
  if (tg.bot_username) {
    return sprintf(__('@%1$s · %2$d-digit OTP', 'wp-sms'), tg.bot_username, tg.code_length ?? 6);
  }
  return __('OTP via Telegram bot', 'wp-sms');
}

function getLineMfaSummary(line: LineSettings): string {
  if (!line.enabled) {
    return __('Send verification codes via LINE Official Account', 'wp-sms');
  }
  return sprintf(__('%d-digit OTP via LINE', 'wp-sms'), line.code_length ?? 6);
}

export function Channels({ settings, onUpdate }: ChannelsProps) {
  const [editingChannel, setEditingChannel] = useState<ChannelId | null>(null);
  const [editingSocial, setEditingSocial] = useState<string | null>(null);
  const [editingTelegramMfa, setEditingTelegramMfa] = useState(false);
  const [editingLineMfa, setEditingLineMfa] = useState(false);
  const socialSettings = settings.social ?? {};
  const telegramSettings = settings.telegram ?? {} as TelegramSettings;
  const lineSettings = settings.line ?? {} as LineSettings;

  function updatePhone(partial: Partial<PhoneChannelSettings>) {
    onUpdate('phone', { ...settings.phone, ...partial });
  }

  function updateEmail(partial: Partial<EmailChannelSettings>) {
    onUpdate('email', { ...settings.email, ...partial });
  }

  function updateTelegram(partial: Partial<TelegramSettings>) {
    onUpdate('telegram', { ...telegramSettings, ...partial });
  }

  function updateLine(partial: Partial<LineSettings>) {
    onUpdate('line', { ...lineSettings, ...partial });
  }

  const phoneUsage = settings.phone.usage ?? 'login';
  const emailUsage = settings.email.usage ?? 'login';

  // Determine cross-reference states
  const phoneInLogin = settings.phone.enabled && phoneUsage === 'login';
  const phoneInMfa = settings.phone.enabled && phoneUsage === 'mfa';
  const emailInLogin = settings.email.enabled && emailUsage === 'login';
  const emailInMfa = settings.email.enabled && emailUsage === 'mfa';

  const authEnabled = !!settings.auth_enabled;

  return (
    <>
      <PageHeader icon={LogIn} title={__('Channels', 'wp-sms')} />

      <div className="mt-4 flex items-center justify-between rounded-lg border bg-card px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
            <Shield className="h-4 w-4 text-primary" />
          </div>
          <div>
            <h3 className="text-sm font-medium">{__('Authentication', 'wp-sms')}</h3>
            <p className="text-xs text-muted-foreground">
              {authEnabled
                ? __('Auth pages are live. Login, registration, and account pages are active.', 'wp-sms')
                : __('Enable custom login, registration, and account pages.', 'wp-sms')}
            </p>
          </div>
        </div>
        <Switch
          checked={authEnabled}
          onCheckedChange={(v) => onUpdate('auth_enabled', v)}
        />
      </div>

      <div className={`mt-4 grid gap-6 lg:grid-cols-2 ${!authEnabled ? 'opacity-60' : ''}`}>
        {/* Left Column — Sign-in Methods */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{__('Sign-in Methods', 'wp-sms')}</CardTitle>
              <CardDescription>{__('Configure how users sign in to your application', 'wp-sms')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-0">
              {/* Phone */}
              <ChannelRow
                icon={Smartphone}
                title={__('Phone', 'wp-sms')}
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
                crossRefLabel={phoneInMfa ? __('Currently used for MFA', 'wp-sms') : undefined}
                onCrossRefAction={phoneInMfa ? () => updatePhone({ usage: 'login' }) : undefined}
                crossRefActionLabel={phoneInMfa ? __('Move to sign-in', 'wp-sms') : undefined}
              />

              <Separator />

              {/* Email */}
              <ChannelRow
                icon={Mail}
                title={__('Email', 'wp-sms')}
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
                crossRefLabel={emailInMfa ? __('Currently used for MFA', 'wp-sms') : undefined}
                onCrossRefAction={emailInMfa ? () => updateEmail({ usage: 'login' }) : undefined}
                crossRefActionLabel={emailInMfa ? __('Move to sign-in', 'wp-sms') : undefined}
              />

              <Separator />

              {/* Password */}
              <ChannelRow
                icon={KeyRound}
                title={__('Password', 'wp-sms')}
                description={__('Traditional username & password', 'wp-sms')}
                enabled={settings.password.enabled}
                onToggle={(v) => onUpdate('password', { ...settings.password, enabled: v })}
                onConfigure={() => setEditingChannel('password')}
              />
            </CardContent>
          </Card>

          {/* Social Connections */}
          <Card>
            <CardHeader>
              <CardTitle>{__('Social Connections', 'wp-sms')}</CardTitle>
              <CardDescription>{__('Allow users to sign in with social accounts', 'wp-sms')}</CardDescription>
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
                      description={isEnabled && providerSettings.client_id ? __('Configured', 'wp-sms') : undefined}
                    />
                  </div>
                );
              })}
            </CardContent>

            {/* Profile Data Sync */}
            <div className="border-t px-6 py-4">
              <div className="space-y-2">
                <Label className="text-sm font-medium">{__('Profile Data Sync', 'wp-sms')}</Label>
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
                    <span className="text-sm">{__('Only at registration', 'wp-sms')}</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <RadioGroupItem value="every_login" />
                    <span className="text-sm">{__('Every login', 'wp-sms')}</span>
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
              <CardTitle>{__('Multi-Factor Authentication', 'wp-sms')}</CardTitle>
              <CardDescription>{__('Add an extra layer of security', 'wp-sms')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-0">
              {/* Phone MFA */}
              <ChannelRow
                icon={Smartphone}
                title={__('Phone MFA', 'wp-sms')}
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
                crossRefLabel={phoneInLogin ? __('Currently used for sign-in', 'wp-sms') : undefined}
                onCrossRefAction={phoneInLogin ? () => updatePhone({ usage: 'mfa' }) : undefined}
                crossRefActionLabel={phoneInLogin ? __('Move to MFA', 'wp-sms') : undefined}
              />

              <Separator />

              {/* Email MFA */}
              <ChannelRow
                icon={Mail}
                title={__('Email MFA', 'wp-sms')}
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
                crossRefLabel={emailInLogin ? __('Currently used for sign-in', 'wp-sms') : undefined}
                onCrossRefAction={emailInLogin ? () => updateEmail({ usage: 'mfa' }) : undefined}
                crossRefActionLabel={emailInLogin ? __('Move to MFA', 'wp-sms') : undefined}
              />

              <Separator />

              {/* Telegram MFA */}
              <ChannelRow
                icon={Send}
                title={__('Telegram MFA', 'wp-sms')}
                description={getTelegramMfaSummary(telegramSettings)}
                enabled={!!telegramSettings.enabled}
                onToggle={(v) => updateTelegram({ enabled: v })}
                onConfigure={() => setEditingTelegramMfa(true)}
              />

              <Separator />

              {/* LINE MFA */}
              <ChannelRow
                icon={MessageCircle}
                title={__('LINE MFA', 'wp-sms')}
                description={getLineMfaSummary(lineSettings)}
                enabled={!!lineSettings.enabled}
                onToggle={(v) => updateLine({ enabled: v })}
                onConfigure={() => setEditingLineMfa(true)}
              />

              <Separator />

              {/* TOTP (Authenticator App) */}
              <ChannelRow
                icon={KeyRound}
                title={__('Authenticator App', 'wp-sms')}
                description={settings.totp.enabled ? __('TOTP — Google Authenticator, Authy, 1Password', 'wp-sms') : undefined}
                enabled={settings.totp.enabled}
                onToggle={(v) => onUpdate('totp', { ...settings.totp, enabled: v })}
              />

              <Separator />

              {/* Passkey */}
              <ChannelRow
                icon={Fingerprint}
                title={__('Passkey', 'wp-sms')}
                description={settings.passkey?.enabled ? __('Fingerprint, Face ID, or security key', 'wp-sms') : undefined}
                enabled={!!settings.passkey?.enabled}
                onToggle={(v) => onUpdate('passkey', { ...settings.passkey, enabled: v })}
              />
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Social Settings Panel */}
      {editingSocial && (
        <SocialSettingsPanel
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
      <Drawer open={editingTelegramMfa} onOpenChange={setEditingTelegramMfa}>
        <DrawerContent className="sm:max-w-md overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
                <Send className="h-4 w-4 text-muted-foreground" />
              </div>
              {__('Telegram MFA Settings', 'wp-sms')}
            </DrawerTitle>
            <DrawerDescription>
              {__('Send MFA verification codes to users via a Telegram bot. Users link their account by starting a conversation with your bot.', 'wp-sms')}
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
              {__('Create a bot with @BotFather', 'wp-sms')}
            </a>

            <Field>
              <FieldLabel htmlFor="tg-bot-token">{__('Bot Token', 'wp-sms')}</FieldLabel>
              <Input
                id="tg-bot-token"
                type="password"
                value={telegramSettings.bot_token ?? ''}
                onChange={(e) => updateTelegram({ bot_token: e.target.value })}
                placeholder="123456789:ABCdefGhIjKlmNoPqRsTuVwXyZ"
              />
              <FieldDescription>
                {__('The API token from @BotFather. Go to BotFather > select your bot > API Token.', 'wp-sms')}
              </FieldDescription>
            </Field>

            {telegramSettings.bot_username && (
              <div className="rounded-md border bg-muted/50 p-3">
                <div className="text-xs font-medium text-muted-foreground">{__('Connected Bot', 'wp-sms')}</div>
                <div className="text-sm font-medium mt-1">@{telegramSettings.bot_username}</div>
              </div>
            )}

            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">{__('How it works', 'wp-sms')}</div>
              <ul className="list-disc list-inside space-y-1">
                <li className="text-xs text-muted-foreground">{__('Users who sign in with Telegram Login are auto-enrolled for Telegram MFA', 'wp-sms')}</li>
                <li className="text-xs text-muted-foreground">{__('Other users can link their Telegram account by clicking a deep link to your bot', 'wp-sms')}</li>
                <li className="text-xs text-muted-foreground">{__('During MFA, a verification code is sent as a bot message in Telegram', 'wp-sms')}</li>
              </ul>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="tg-code-length">{__('Code Length', 'wp-sms')}</FieldLabel>
              <div className="flex gap-2">
                {[4, 6].map((len) => (
                  <button
                    key={len}
                    type="button"
                    onClick={() => updateTelegram({ code_length: len })}
                    className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${(telegramSettings.code_length ?? 6) === len ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-accent'}`}
                  >
                    {sprintf(__('%d digits', 'wp-sms'), len)}
                  </button>
                ))}
              </div>
            </Field>

            <Field>
              <FieldLabel htmlFor="tg-expiry">{__('Code Expiry (seconds)', 'wp-sms')}</FieldLabel>
              <Input
                id="tg-expiry"
                type="number"
                min={60}
                max={900}
                value={telegramSettings.expiry ?? 300}
                onChange={(e) => updateTelegram({ expiry: parseInt(e.target.value) || 300 })}
              />
              <FieldDescription>
                {__('How long a verification code is valid. Default: 300 seconds (5 minutes).', 'wp-sms')}
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="tg-cooldown">{__('Cooldown (seconds)', 'wp-sms')}</FieldLabel>
              <Input
                id="tg-cooldown"
                type="number"
                min={10}
                max={300}
                value={telegramSettings.cooldown ?? 60}
                onChange={(e) => updateTelegram({ cooldown: parseInt(e.target.value) || 60 })}
              />
              <FieldDescription>
                {__('Minimum wait time between code requests. Prevents abuse.', 'wp-sms')}
              </FieldDescription>
            </Field>
          </div>
        </DrawerContent>
      </Drawer>

      {/* LINE MFA Settings Drawer */}
      <Drawer open={editingLineMfa} onOpenChange={setEditingLineMfa}>
        <DrawerContent className="sm:max-w-md overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
                <MessageCircle className="h-4 w-4 text-muted-foreground" />
              </div>
              {__('LINE MFA Settings', 'wp-sms')}
            </DrawerTitle>
            <DrawerDescription>
              {__('Send MFA verification codes to users via a LINE Official Account. Users link their account by messaging your bot.', 'wp-sms')}
            </DrawerDescription>
          </DrawerHeader>

          <div className="space-y-6 px-4 pb-4">
            <a
              href="https://developers.line.biz/console/"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-2 text-sm text-primary hover:underline"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              {__('LINE Developers Console', 'wp-sms')}
            </a>

            <Field>
              <FieldLabel htmlFor="line-bot-id">{__('Bot Basic ID', 'wp-sms')}</FieldLabel>
              <Input
                id="line-bot-id"
                type="text"
                dir="ltr"
                value={lineSettings.bot_basic_id ?? ''}
                onChange={(e) => updateLine({ bot_basic_id: e.target.value })}
                placeholder={__('@123abcde', 'wp-sms')}
              />
              <FieldDescription>
                {__('Found in LINE Developers Console under your Messaging API channel. Used for enrollment deep links.', 'wp-sms')}
              </FieldDescription>
            </Field>

            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">{__('How it works', 'wp-sms')}</div>
              <ul className="list-disc list-inside space-y-1">
                <li className="text-xs text-muted-foreground">{__('Users who sign in with LINE Login are auto-enrolled for LINE MFA', 'wp-sms')}</li>
                <li className="text-xs text-muted-foreground">{__('Other users can link their LINE account by clicking a deep link to your Official Account', 'wp-sms')}</li>
                <li className="text-xs text-muted-foreground">{__('During MFA, a verification code is sent as a message in LINE', 'wp-sms')}</li>
              </ul>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="line-code-length">{__('Code Length', 'wp-sms')}</FieldLabel>
              <div className="flex gap-2">
                {[4, 6].map((len) => (
                  <button
                    key={len}
                    type="button"
                    onClick={() => updateLine({ code_length: len })}
                    className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${(lineSettings.code_length ?? 6) === len ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-accent'}`}
                  >
                    {sprintf(__('%d digits', 'wp-sms'), len)}
                  </button>
                ))}
              </div>
            </Field>

            <Field>
              <FieldLabel htmlFor="line-expiry">{__('Code Expiry (seconds)', 'wp-sms')}</FieldLabel>
              <Input
                id="line-expiry"
                type="number"
                min={60}
                max={900}
                value={lineSettings.expiry ?? 300}
                onChange={(e) => updateLine({ expiry: parseInt(e.target.value) || 300 })}
              />
              <FieldDescription>
                {__('How long a verification code is valid. Default: 300 seconds (5 minutes).', 'wp-sms')}
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="line-cooldown">{__('Cooldown (seconds)', 'wp-sms')}</FieldLabel>
              <Input
                id="line-cooldown"
                type="number"
                min={10}
                max={300}
                value={lineSettings.cooldown ?? 60}
                onChange={(e) => updateLine({ cooldown: parseInt(e.target.value) || 60 })}
              />
              <FieldDescription>
                {__('Minimum wait time between code requests. Prevents abuse.', 'wp-sms')}
              </FieldDescription>
            </Field>
          </div>
        </DrawerContent>
      </Drawer>

      {/* Settings Panel */}
      <ChannelSettingsPanel
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
