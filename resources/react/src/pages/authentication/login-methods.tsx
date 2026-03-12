import { MethodCard } from '@/components/method-card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { PRIMARY_METHODS, toggleArrayItem } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface LoginMethodsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

interface OtpConfigProps {
  prefix: 'otp_sms' | 'otp_email';
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

function OtpConfig({ prefix, settings, onUpdate }: OtpConfigProps) {
  const lengthKey = `${prefix}_length` as keyof AuthSettings;
  const expiryKey = `${prefix}_expiry` as keyof AuthSettings;

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field>
        <FieldLabel htmlFor={lengthKey}>Code Length</FieldLabel>
        <Input
          id={lengthKey}
          type="number"
          min={4}
          max={8}
          value={settings[lengthKey] as number}
          onChange={(e) => onUpdate(lengthKey, Number(e.target.value))}
        />
        <FieldDescription>Number of digits in the OTP code (4-8)</FieldDescription>
      </Field>
      <Field>
        <FieldLabel htmlFor={expiryKey}>Expiry (seconds)</FieldLabel>
        <Input
          id={expiryKey}
          type="number"
          min={60}
          max={3600}
          value={settings[expiryKey] as number}
          onChange={(e) => onUpdate(expiryKey, Number(e.target.value))}
        />
        <FieldDescription>How long the code remains valid (60-3600)</FieldDescription>
      </Field>
    </div>
  );
}

export function LoginMethods({ settings, onUpdate }: LoginMethodsProps) {
  const methods = settings.primary_methods;

  return (
    <div className="space-y-4">
      {PRIMARY_METHODS.map((method) => (
        <MethodCard
          key={method.id}
          title={method.label}
          description={method.description}
          icon={method.icon}
          enabled={methods.includes(method.id)}
          onToggle={(enabled) => onUpdate('primary_methods', toggleArrayItem(methods, method.id, enabled))}
        >
          {method.id === 'phone_otp' && (
            <OtpConfig prefix="otp_sms" settings={settings} onUpdate={onUpdate} />
          )}
          {method.id === 'email_otp' && (
            <OtpConfig prefix="otp_email" settings={settings} onUpdate={onUpdate} />
          )}
          {method.id === 'magic_link' && (
            <div className="max-w-xs">
              <Field>
                <FieldLabel htmlFor="magic_link_expiry">Link Expiry (seconds)</FieldLabel>
                <Input
                  id="magic_link_expiry"
                  type="number"
                  min={60}
                  max={3600}
                  value={settings.magic_link_expiry}
                  onChange={(e) => onUpdate('magic_link_expiry', Number(e.target.value))}
                />
                <FieldDescription>How long the magic link remains valid (60-3600)</FieldDescription>
              </Field>
            </div>
          )}
        </MethodCard>
      ))}
    </div>
  );
}
