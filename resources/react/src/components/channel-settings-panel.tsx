import { __, sprintf } from '@wordpress/i18n';
import { Smartphone, Mail, KeyRound } from 'lucide-react';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { CHANNELS } from '@/lib/constants';
import type { ChannelId } from '@/lib/constants';
import { cn } from '@/lib/utils';
import { useGateways } from '@/hooks/use-gateways';
import type { PhoneChannelSettings, EmailChannelSettings, PasswordSettings, DeliveryChannel } from '@/lib/api';

interface ChannelSettingsPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  channelId: ChannelId;
  settings: PhoneChannelSettings | EmailChannelSettings | PasswordSettings;
  onUpdate: (partial: Partial<PhoneChannelSettings | EmailChannelSettings | PasswordSettings>) => void;
}

const CHANNEL_META: Record<ChannelId, { icon: typeof Smartphone; label: string }> = {
  phone: { icon: Smartphone, label: __('Phone', 'wp-sms') },
  email: { icon: Mail, label: __('Email', 'wp-sms') },
  password: { icon: KeyRound, label: __('Password', 'wp-sms') },
};

function PasswordContent({
  settings,
  onUpdate,
}: {
  settings: PasswordSettings;
  onUpdate: (partial: Partial<PasswordSettings>) => void;
}) {
  return (
    <div className="space-y-6 px-4 pb-4">
      <div className="space-y-2">
        <Label className="text-sm font-medium">{__('Options', 'wp-sms')}</Label>
        <div className="space-y-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.required_at_signup}
              onCheckedChange={(checked) => onUpdate({ required_at_signup: !!checked })}
            />
            <span className="text-sm">{__('Required at sign up', 'wp-sms')}</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.allow_sign_in}
              onCheckedChange={(checked) => onUpdate({ allow_sign_in: !!checked })}
            />
            <span className="text-sm">{__('Password login', 'wp-sms')}</span>
          </label>
          <p className="text-xs text-muted-foreground ltr:pl-6 rtl:pr-6">
            {__('Users can sign in using their email, username, or phone with a password', 'wp-sms')}
          </p>
        </div>
      </div>
    </div>
  );
}

function ChannelContent({
  channelId,
  settings,
  onUpdate,
}: {
  channelId: 'phone' | 'email';
  settings: PhoneChannelSettings & EmailChannelSettings;
  onUpdate: (partial: Partial<PhoneChannelSettings & EmailChannelSettings>) => void;
}) {
  const channelConfig = CHANNELS.find((c) => c.id === channelId)!;
  const { gateways } = useGateways();
  const deliveryChannel = channelId === 'phone'
    ? ((settings as PhoneChannelSettings).delivery_channel ?? 'sms')
    : 'email';
  const isWhatsApp = deliveryChannel === 'whatsapp';
  const configuredGateways = gateways.filter(
    (g) => g.configured_channels.includes(deliveryChannel),
  );

  return (
    <div className="space-y-6 px-4 pb-4">
      {/* Usage */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">{__('Usage', 'wp-sms')}</Label>
        <RadioGroup
          value={settings.usage}
          onValueChange={(v) => {
            if (v === 'login' || v === 'mfa') onUpdate({ usage: v });
          }}
          className="flex gap-4"
        >
          <label className="flex items-center gap-2 cursor-pointer">
            <RadioGroupItem value="login" />
            <span className="text-sm">{__('Login / Register', 'wp-sms')}</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <RadioGroupItem value="mfa" />
            <span className="text-sm">{__('MFA', 'wp-sms')}</span>
          </label>
        </RadioGroup>
      </div>

      <Separator />

      {/* Verification Methods */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">{__('Verification Methods', 'wp-sms')}</Label>
        <div className="space-y-2">
          {channelConfig.verificationMethods
            .filter((vm) => !(isWhatsApp && vm.value === 'magic_link'))
            .map((vm) => (
            <label key={vm.value} className="flex items-center gap-2 cursor-pointer">
              <Checkbox
                checked={settings.verification_methods?.includes(vm.value as 'otp' | 'magic_link')}
                onCheckedChange={(checked) => {
                  const current = settings.verification_methods ?? ['otp'];
                  const next = checked
                    ? [...current, vm.value]
                    : current.filter((v) => v !== vm.value);
                  if (next.length > 0) {
                    onUpdate({ verification_methods: next as ('otp' | 'magic_link')[] });
                  }
                }}
              />
              <span className="text-sm">{vm.label}</span>
            </label>
          ))}
          {isWhatsApp && (
            <p className="text-xs text-muted-foreground">{__('WhatsApp supports OTP codes only', 'wp-sms')}</p>
          )}
        </div>
      </div>

      {/* Delivery Channels (phone only) */}
      {channelConfig.deliveryChannels && (
        <>
          <Separator />
          <div className="space-y-2">
            <Label className="text-sm font-medium">{__('Delivery Channel', 'wp-sms')}</Label>
            <RadioGroup
              value={(settings as PhoneChannelSettings).delivery_channel}
              onValueChange={(v) => {
                const update: Partial<PhoneChannelSettings> = { delivery_channel: v as DeliveryChannel };
                // WhatsApp doesn't support magic links — strip it and reset incompatible gateway.
                if (v === 'whatsapp') {
                  const methods = (settings.verification_methods ?? ['otp']).filter((m) => m !== 'magic_link');
                  update.verification_methods = methods.length > 0 ? methods : ['otp'];
                }
                // Clear otp_gateway if it doesn't support the new delivery channel.
                if (settings.otp_gateway) {
                  const gw = gateways.find((g) => g.id === settings.otp_gateway);
                  if (gw && !gw.supported_channels.includes(v)) {
                    update.otp_gateway = null;
                  }
                }
                onUpdate(update);
              }}
              className="space-y-2"
            >
              {channelConfig.deliveryChannels.map((dc) => {
                const hasGateway = dc.available && gateways.some((g) => g.configured_channels.includes(dc.value));
                const enabled = dc.available && hasGateway;
                return (
                  <label
                    key={dc.value}
                    className={cn(
                      'flex items-center gap-2',
                      enabled ? 'cursor-pointer' : 'cursor-not-allowed',
                    )}
                  >
                    <RadioGroupItem value={dc.value} disabled={!enabled} />
                    <span className="text-sm">{dc.label}</span>
                    {!dc.available && (
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                        {__('Coming Soon', 'wp-sms')}
                      </Badge>
                    )}
                    {dc.available && !hasGateway && (
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                        {__('No gateway', 'wp-sms')}
                      </Badge>
                    )}
                  </label>
                );
              })}
            </RadioGroup>
          </div>
        </>
      )}

      {/* OTP Gateway Override */}
      {configuredGateways.length > 0 && (
        <>
          <Separator />
          <Field>
            <FieldLabel>{__('OTP Gateway', 'wp-sms')}</FieldLabel>
            <Select
              value={settings.otp_gateway ?? '__default__'}
              onValueChange={(v) => onUpdate({ otp_gateway: v === '__default__' ? null : v })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__default__">{__('Use default gateway', 'wp-sms')}</SelectItem>
                {configuredGateways.map((g) => (
                  <SelectItem key={g.id} value={g.id}>{g.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FieldDescription>
              {__('Route OTP/verification messages through a dedicated gateway instead of the channel default', 'wp-sms')}
            </FieldDescription>
          </Field>
        </>
      )}

      <Separator />

      {/* Options */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">{__('Options', 'wp-sms')}</Label>
        <div className="space-y-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.required_at_signup}
              onCheckedChange={(checked) => onUpdate({ required_at_signup: !!checked })}
            />
            <span className="text-sm">{__('Required at sign up', 'wp-sms')}</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.verify_at_signup}
              onCheckedChange={(checked) => onUpdate({ verify_at_signup: !!checked })}
            />
            <span className="text-sm">{__('Verify at sign up', 'wp-sms')}</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.allow_sign_in}
              onCheckedChange={(checked) => onUpdate({ allow_sign_in: !!checked })}
            />
            <span className="text-sm">{__('Passwordless login', 'wp-sms')}</span>
          </label>
          <p className="text-xs text-muted-foreground ltr:pl-6 rtl:pr-6">
            {__('Users can sign in with a one-time code or magic link, without needing a password', 'wp-sms')}
          </p>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.reverify_on_change}
              onCheckedChange={(checked) => onUpdate({ reverify_on_change: !!checked })}
            />
            <span className="text-sm">{__('Re-verify on change', 'wp-sms')}</span>
          </label>
        </div>
      </div>

      <Separator />

      {/* Code Settings */}
      <div className="space-y-4">
        <Label className="text-sm font-medium">{__('Code Settings', 'wp-sms')}</Label>
        {settings.verification_methods?.includes('otp') && (
          <Field>
            <FieldLabel>{__('Code Length', 'wp-sms')}</FieldLabel>
            <div className="flex gap-2">
              {[4, 6].map((len) => (
                <button
                  key={len}
                  type="button"
                  aria-pressed={(settings.code_length ?? 6) === len}
                  onClick={() => onUpdate({ code_length: len })}
                  className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                    (settings.code_length ?? 6) === len
                      ? 'border-primary bg-primary/10 text-primary'
                      : 'border-border hover:bg-accent'
                  }`}
                >
                  {sprintf(__('%d digits', 'wp-sms'), len)}
                </button>
              ))}
            </div>
          </Field>
        )}
        <Field>
          <FieldLabel htmlFor={`${channelId}-panel-expiry`}>{__('Code Expiry (seconds)', 'wp-sms')}</FieldLabel>
          <Input
            id={`${channelId}-panel-expiry`}
            type="number"
            min={60}
            max={3600}
            value={settings.expiry}
            onChange={(e) => onUpdate({ expiry: Number(e.target.value) })}
          />
          <FieldDescription>{__('How long the code/link remains valid (60-3600)', 'wp-sms')}</FieldDescription>
        </Field>
      </div>
    </div>
  );
}

export function ChannelSettingsPanel({
  open,
  onOpenChange,
  channelId,
  settings,
  onUpdate,
}: ChannelSettingsPanelProps) {
  const meta = CHANNEL_META[channelId];
  const Icon = meta.icon;

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
              <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            {sprintf(__('%s Settings', 'wp-sms'), meta.label)}
          </DrawerTitle>
          <DrawerDescription>
            {sprintf(__('Configure %s settings', 'wp-sms'), meta.label.toLowerCase())}
          </DrawerDescription>
        </DrawerHeader>

        {channelId === 'password' ? (
          <PasswordContent
            settings={settings as PasswordSettings}
            onUpdate={onUpdate as (partial: Partial<PasswordSettings>) => void}
          />
        ) : (
          <ChannelContent
            channelId={channelId}
            settings={settings as PhoneChannelSettings & EmailChannelSettings}
            onUpdate={onUpdate as (partial: Partial<PhoneChannelSettings & EmailChannelSettings>) => void}
          />
        )}
      </DrawerContent>
    </Drawer>
  );
}
