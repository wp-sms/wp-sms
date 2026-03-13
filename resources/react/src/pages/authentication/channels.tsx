import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ChannelRow } from '@/components/channel-row';
import { ChannelSettingsSheet } from '@/components/channel-settings-sheet';
import { SocialSettingsSheet } from '@/components/social-settings-sheet';
import type { ChannelId } from '@/lib/constants';
import { GoogleIcon, AppleIcon, LinkedInIcon, FacebookIcon, MicrosoftIcon, GitHubIcon, TwitterIcon } from '@/components/icons/social';
import { SOCIAL_METHODS } from '@/lib/constants';
import { Smartphone, Mail, KeyRound, Fingerprint } from 'lucide-react';
import type { AuthSettings, PhoneChannelSettings, EmailChannelSettings } from '@/lib/api';

interface ChannelsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const SOCIAL_ICONS: Record<string, React.ComponentType<React.SVGProps<SVGSVGElement>>> = {
  google: GoogleIcon,
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

export function Channels({ settings, onUpdate }: ChannelsProps) {
  const [editingChannel, setEditingChannel] = useState<ChannelId | null>(null);
  const [editingSocial, setEditingSocial] = useState<string | null>(null);
  const socialSettings = settings.social ?? {};

  function updatePhone(partial: Partial<PhoneChannelSettings>) {
    onUpdate('phone', { ...settings.phone, ...partial });
  }

  function updateEmail(partial: Partial<EmailChannelSettings>) {
    onUpdate('email', { ...settings.email, ...partial });
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
