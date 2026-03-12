import { ChannelCard } from '@/components/channel-card';
import { MethodCard } from '@/components/method-card';
import { Checkbox } from '@/components/ui/checkbox';
import { CHANNELS } from '@/lib/constants';
import { KeyRound } from 'lucide-react';
import type { AuthSettings, PhoneChannelSettings, EmailChannelSettings } from '@/lib/api';

interface ChannelsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function Channels({ settings, onUpdate }: ChannelsProps) {
  const phoneConfig = CHANNELS[0];
  const emailConfig = CHANNELS[1];

  function updatePhone(partial: Partial<PhoneChannelSettings>) {
    onUpdate('phone', { ...settings.phone, ...partial });
  }

  function updateEmail(partial: Partial<EmailChannelSettings>) {
    onUpdate('email', { ...settings.email, ...partial });
  }

  return (
    <div className="space-y-4">
      {/* Phone Channel */}
      <ChannelCard
        title={phoneConfig.label}
        icon={phoneConfig.icon}
        enabled={settings.phone.enabled}
        onToggle={(v) => updatePhone({ enabled: v })}
        usage={settings.phone.usage}
        onUsageChange={(v) => updatePhone({ usage: v })}
        verificationMethods={phoneConfig.verificationMethods}
        enabledVerificationMethods={settings.phone.verification_methods}
        onVerificationMethodsChange={(v) => updatePhone({ verification_methods: v })}
        deliveryChannels={phoneConfig.deliveryChannels}
        activeDeliveryChannel={settings.phone.delivery_channel}
        onDeliveryChannelChange={(v) => updatePhone({ delivery_channel: v })}
        requiredAtSignup={settings.phone.required_at_signup}
        onRequiredAtSignupChange={(v) => updatePhone({ required_at_signup: v })}
        verifyAtSignup={settings.phone.verify_at_signup}
        onVerifyAtSignupChange={(v) => updatePhone({ verify_at_signup: v })}
        allowSignIn={settings.phone.allow_sign_in}
        onAllowSignInChange={(v) => updatePhone({ allow_sign_in: v })}
        codeLength={settings.phone.code_length}
        onCodeLengthChange={(v) => updatePhone({ code_length: v })}
        expiry={settings.phone.expiry}
        onExpiryChange={(v) => updatePhone({ expiry: v })}
      />

      {/* Email Channel */}
      <ChannelCard
        title={emailConfig.label}
        icon={emailConfig.icon}
        enabled={settings.email.enabled}
        onToggle={(v) => updateEmail({ enabled: v })}
        usage={settings.email.usage}
        onUsageChange={(v) => updateEmail({ usage: v })}
        verificationMethods={emailConfig.verificationMethods}
        enabledVerificationMethods={settings.email.verification_methods}
        onVerificationMethodsChange={(v) => updateEmail({ verification_methods: v })}
        requiredAtSignup={settings.email.required_at_signup}
        onRequiredAtSignupChange={(v) => updateEmail({ required_at_signup: v })}
        verifyAtSignup={settings.email.verify_at_signup}
        onVerifyAtSignupChange={(v) => updateEmail({ verify_at_signup: v })}
        allowSignIn={settings.email.allow_sign_in}
        onAllowSignInChange={(v) => updateEmail({ allow_sign_in: v })}
        codeLength={settings.email.code_length}
        onCodeLengthChange={(v) => updateEmail({ code_length: v })}
        expiry={settings.email.expiry}
        onExpiryChange={(v) => updateEmail({ expiry: v })}
      />

      {/* Password Card */}
      <MethodCard
        title="Password"
        description="Traditional username & password login"
        icon={KeyRound}
        enabled={settings.password.enabled}
        onToggle={(v) => onUpdate('password', { ...settings.password, enabled: v })}
      >
        <div className="space-y-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.password.required_at_signup}
              onCheckedChange={(checked) => onUpdate('password', { ...settings.password, required_at_signup: !!checked })}
            />
            <span className="text-sm">Required at sign up</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.password.allow_sign_in}
              onCheckedChange={(checked) => onUpdate('password', { ...settings.password, allow_sign_in: !!checked })}
            />
            <span className="text-sm">Allow to sign in</span>
          </label>
        </div>
      </MethodCard>
    </div>
  );
}
