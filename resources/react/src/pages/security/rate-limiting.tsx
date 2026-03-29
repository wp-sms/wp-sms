import { __, sprintf } from '@wordpress/i18n';
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
            <FieldLabel htmlFor={attemptsId}>{__('Max Attempts', 'wp-sms')}</FieldLabel>
            <Input
              id={attemptsId}
              type="number"
              min={1}
              max={20}
              value={maxAttempts}
              onChange={(e) => onChangeAttempts(Number(e.target.value))}
            />
            <FieldDescription>{__('Maximum verification attempts before lockout', 'wp-sms')}</FieldDescription>
          </Field>
          <Field>
            <FieldLabel htmlFor={cooldownId}>{__('Cooldown (seconds)', 'wp-sms')}</FieldLabel>
            <Input
              id={cooldownId}
              type="number"
              min={10}
              max={3600}
              value={cooldown}
              onChange={(e) => onChangeCooldown(Number(e.target.value))}
            />
            <FieldDescription>{__('Wait time after reaching max attempts', 'wp-sms')}</FieldDescription>
          </Field>
        </div>
        <p className="text-xs text-muted-foreground mt-2 pt-2 border-t border-border/50">
          {sprintf(__('Users will be locked out for %1$ds after %2$d failed attempts.', 'wp-sms'), cooldown, maxAttempts)}
        </p>
      </CardContent>
    </Card>
  );
}

export function RateLimiting({ settings, onUpdate }: RateLimitingProps) {
  return (
    <div className="space-y-4">
      <RateLimitCard
        title={__('Phone Channel Limits', 'wp-sms')}
        description={__('Limit the number of phone verification attempts', 'wp-sms')}
        icon={Smartphone}
        attemptsId="phone_max_attempts"
        cooldownId="phone_cooldown"
        maxAttempts={settings.phone.max_attempts}
        cooldown={settings.phone.cooldown}
        onChangeAttempts={(v) => onUpdate('phone', { ...settings.phone, max_attempts: v })}
        onChangeCooldown={(v) => onUpdate('phone', { ...settings.phone, cooldown: v })}
      />

      <RateLimitCard
        title={__('Email Channel Limits', 'wp-sms')}
        description={__('Limit the number of email verification attempts', 'wp-sms')}
        icon={Mail}
        attemptsId="email_max_attempts"
        cooldownId="email_cooldown"
        maxAttempts={settings.email.max_attempts}
        cooldown={settings.email.cooldown}
        onChangeAttempts={(v) => onUpdate('email', { ...settings.email, max_attempts: v })}
        onChangeCooldown={(v) => onUpdate('email', { ...settings.email, cooldown: v })}
      />
    </div>
  );
}
