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
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { CHANNELS } from '@/lib/constants';
import type { ChannelId } from '@/lib/constants';
import { cn } from '@/lib/utils';
import type { PhoneChannelSettings, EmailChannelSettings, PasswordSettings } from '@/lib/api';

interface ChannelSettingsSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  channelId: ChannelId;
  settings: PhoneChannelSettings | EmailChannelSettings | PasswordSettings;
  onUpdate: (partial: Partial<PhoneChannelSettings | EmailChannelSettings | PasswordSettings>) => void;
}

const CHANNEL_META: Record<ChannelId, { icon: typeof Smartphone; label: string }> = {
  phone: { icon: Smartphone, label: 'Phone' },
  email: { icon: Mail, label: 'Email' },
  password: { icon: KeyRound, label: 'Password' },
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
        <Label className="text-sm font-medium">Options</Label>
        <div className="space-y-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.required_at_signup}
              onCheckedChange={(checked) => onUpdate({ required_at_signup: !!checked })}
            />
            <span className="text-sm">Required at sign up</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.allow_sign_in}
              onCheckedChange={(checked) => onUpdate({ allow_sign_in: !!checked })}
            />
            <span className="text-sm">Allow to sign in</span>
          </label>
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

  return (
    <div className="space-y-6 px-4 pb-4">
      {/* Usage */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">Usage</Label>
        <RadioGroup
          value={settings.usage}
          onValueChange={(v) => {
            if (v === 'login' || v === 'mfa') onUpdate({ usage: v });
          }}
          className="flex gap-4"
        >
          <label className="flex items-center gap-2 cursor-pointer">
            <RadioGroupItem value="login" />
            <span className="text-sm">Login / Register</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <RadioGroupItem value="mfa" />
            <span className="text-sm">MFA</span>
          </label>
        </RadioGroup>
      </div>

      <Separator />

      {/* Verification Methods */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">Verification Methods</Label>
        <div className="space-y-2">
          {channelConfig.verificationMethods.map((vm) => (
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
        </div>
      </div>

      {/* Delivery Channels (phone only) */}
      {channelConfig.deliveryChannels && (
        <>
          <Separator />
          <div className="space-y-2">
            <Label className="text-sm font-medium">Delivery Channel</Label>
            <RadioGroup
              value={(settings as PhoneChannelSettings).delivery_channel}
              onValueChange={(v) => onUpdate({ delivery_channel: v as 'sms' | 'whatsapp' | 'viber' })}
              className="space-y-2"
            >
              {channelConfig.deliveryChannels.map((dc) => (
                <label
                  key={dc.value}
                  className={cn(
                    'flex items-center gap-2',
                    dc.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-50',
                  )}
                >
                  <RadioGroupItem value={dc.value} disabled={!dc.available} />
                  <span className="text-sm">{dc.label}</span>
                  {!dc.available && (
                    <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                      Coming Soon
                    </Badge>
                  )}
                </label>
              ))}
            </RadioGroup>
          </div>
        </>
      )}

      <Separator />

      {/* Options */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">Options</Label>
        <div className="space-y-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.required_at_signup}
              onCheckedChange={(checked) => onUpdate({ required_at_signup: !!checked })}
            />
            <span className="text-sm">Required at sign up</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.verify_at_signup}
              onCheckedChange={(checked) => onUpdate({ verify_at_signup: !!checked })}
            />
            <span className="text-sm">Verify at sign up</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Checkbox
              checked={settings.allow_sign_in}
              onCheckedChange={(checked) => onUpdate({ allow_sign_in: !!checked })}
            />
            <span className="text-sm">Allow to sign in</span>
          </label>
        </div>
      </div>

      <Separator />

      {/* Code Settings */}
      <div className="space-y-4">
        <Label className="text-sm font-medium">Code Settings</Label>
        <Field>
          <FieldLabel htmlFor={`${channelId}-sheet-code-length`}>Code Length</FieldLabel>
          <Input
            id={`${channelId}-sheet-code-length`}
            type="number"
            min={4}
            max={8}
            value={settings.code_length}
            onChange={(e) => onUpdate({ code_length: Number(e.target.value) })}
          />
          <FieldDescription>Number of digits in the OTP code (4-8)</FieldDescription>
        </Field>
        <Field>
          <FieldLabel htmlFor={`${channelId}-sheet-expiry`}>Expiry (seconds)</FieldLabel>
          <Input
            id={`${channelId}-sheet-expiry`}
            type="number"
            min={60}
            max={3600}
            value={settings.expiry}
            onChange={(e) => onUpdate({ expiry: Number(e.target.value) })}
          />
          <FieldDescription>How long the code/link remains valid (60-3600)</FieldDescription>
        </Field>
      </div>
    </div>
  );
}

export function ChannelSettingsSheet({
  open,
  onOpenChange,
  channelId,
  settings,
  onUpdate,
}: ChannelSettingsSheetProps) {
  const meta = CHANNEL_META[channelId];
  const Icon = meta.icon;

  return (
    <Drawer open={open} onOpenChange={onOpenChange} direction="right">
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
              <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            {meta.label} Settings
          </DrawerTitle>
          <DrawerDescription>
            Configure {meta.label.toLowerCase()} settings
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
