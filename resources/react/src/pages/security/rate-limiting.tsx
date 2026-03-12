import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Smartphone, Mail } from 'lucide-react';
import type { AuthSettings } from '@/lib/api';

interface RateLimitingProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

interface RateLimitCardProps {
  title: string;
  description: string;
  icon?: LucideIcon;
  attemptsId: string;
  cooldownId: string;
  maxAttempts: number;
  cooldown: number;
  onChangeAttempts: (v: number) => void;
  onChangeCooldown: (v: number) => void;
}

function RateLimitCard({ title, description, icon: Icon, attemptsId, cooldownId, maxAttempts, cooldown, onChangeAttempts, onChangeCooldown }: RateLimitCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
          {title}
        </CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field>
            <FieldLabel htmlFor={attemptsId}>Max Attempts</FieldLabel>
            <Input
              id={attemptsId}
              type="number"
              min={1}
              max={20}
              value={maxAttempts}
              onChange={(e) => onChangeAttempts(Number(e.target.value))}
            />
            <FieldDescription>Maximum verification attempts before lockout</FieldDescription>
          </Field>
          <Field>
            <FieldLabel htmlFor={cooldownId}>Cooldown (seconds)</FieldLabel>
            <Input
              id={cooldownId}
              type="number"
              min={10}
              max={3600}
              value={cooldown}
              onChange={(e) => onChangeCooldown(Number(e.target.value))}
            />
            <FieldDescription>Wait time after reaching max attempts</FieldDescription>
          </Field>
        </div>
        <p className="text-xs text-muted-foreground mt-2 pt-2 border-t border-border/50">
          Users will be locked out for {cooldown}s after {maxAttempts} failed attempts.
        </p>
      </CardContent>
    </Card>
  );
}

export function RateLimiting({ settings, onUpdate }: RateLimitingProps) {
  return (
    <div className="space-y-4">
      <RateLimitCard
        title="SMS OTP Limits"
        description="Limit the number of SMS OTP verification attempts"
        icon={Smartphone}
        attemptsId="otp_sms_max_attempts"
        cooldownId="otp_sms_cooldown"
        maxAttempts={settings.otp_sms_max_attempts}
        cooldown={settings.otp_sms_cooldown}
        onChangeAttempts={(v) => onUpdate('otp_sms_max_attempts', v)}
        onChangeCooldown={(v) => onUpdate('otp_sms_cooldown', v)}
      />

      <RateLimitCard
        title="Email OTP Limits"
        description="Limit the number of email OTP verification attempts"
        icon={Mail}
        attemptsId="otp_email_max_attempts"
        cooldownId="otp_email_cooldown"
        maxAttempts={settings.otp_email_max_attempts}
        cooldown={settings.otp_email_cooldown}
        onChangeAttempts={(v) => onUpdate('otp_email_max_attempts', v)}
        onChangeCooldown={(v) => onUpdate('otp_email_cooldown', v)}
      />
    </div>
  );
}
