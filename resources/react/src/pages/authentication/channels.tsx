import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { ChannelRow } from '@/components/channel-row';
import { ChannelSettingsSheet } from '@/components/channel-settings-sheet';
import type { ChannelId } from '@/lib/constants';
import { GoogleIcon, AppleIcon, LinkedInIcon } from '@/components/icons/social';
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
  linkedin: LinkedInIcon,
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
              {SOCIAL_METHODS.map((method, i) => (
                <div key={method.id}>
                  {i > 0 && <Separator />}
                  <ChannelRow
                    icon={SOCIAL_ICONS[method.id]}
                    title={method.label}
                    comingSoon
                  />
                </div>
              ))}
            </CardContent>
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
