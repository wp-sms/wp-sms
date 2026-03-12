import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';

interface VerificationMethod {
  value: string;
  label: string;
}

interface DeliveryChannel {
  value: string;
  label: string;
  available: boolean;
}

interface ChannelCardProps {
  title: string;
  icon: LucideIcon;
  enabled: boolean;
  onToggle: (enabled: boolean) => void;
  usage?: 'login' | 'mfa';
  onUsageChange?: (usage: 'login' | 'mfa') => void;
  verificationMethods?: readonly VerificationMethod[];
  enabledVerificationMethods?: string[];
  onVerificationMethodsChange?: (methods: string[]) => void;
  deliveryChannels?: readonly DeliveryChannel[] | null;
  activeDeliveryChannel?: string;
  onDeliveryChannelChange?: (channel: string) => void;
  requiredAtSignup?: boolean;
  onRequiredAtSignupChange?: (v: boolean) => void;
  verifyAtSignup?: boolean;
  onVerifyAtSignupChange?: (v: boolean) => void;
  allowSignIn?: boolean;
  onAllowSignInChange?: (v: boolean) => void;
  codeLength?: number;
  onCodeLengthChange?: (v: number) => void;
  expiry?: number;
  onExpiryChange?: (v: number) => void;
}

export function ChannelCard({
  title,
  icon: Icon,
  enabled,
  onToggle,
  usage,
  onUsageChange,
  verificationMethods,
  enabledVerificationMethods,
  onVerificationMethodsChange,
  deliveryChannels,
  activeDeliveryChannel,
  onDeliveryChannelChange,
  requiredAtSignup,
  onRequiredAtSignupChange,
  verifyAtSignup,
  onVerifyAtSignupChange,
  allowSignIn,
  onAllowSignInChange,
  codeLength,
  onCodeLengthChange,
  expiry,
  onExpiryChange,
}: ChannelCardProps) {
  return (
    <Card className={cn(
      'transition-shadow duration-150',
      enabled && 'hover:shadow-[var(--shadow-card-hover)] border-l-2 border-l-primary',
      !enabled && 'opacity-50',
    )}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <Icon className="h-4 w-4 text-muted-foreground" />
          {title}
        </CardTitle>
        <Switch
          checked={enabled}
          onCheckedChange={onToggle}
          aria-label={`Toggle ${title}`}
        />
      </CardHeader>

      {enabled && (
        <CardContent className="border-t pt-4 space-y-5">
          {/* Usage radio */}
          {onUsageChange && (
            <div className="space-y-2">
              <Label className="text-sm font-medium text-muted-foreground">Usage</Label>
              <RadioGroup
                value={usage}
                onValueChange={(v) => { if (v === 'login' || v === 'mfa') onUsageChange(v); }}
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
          )}

          {/* Verification methods */}
          {verificationMethods && onVerificationMethodsChange && enabledVerificationMethods && (
            <div className="space-y-2">
              <Label className="text-sm font-medium text-muted-foreground">Verification</Label>
              <div className="flex gap-4">
                {verificationMethods.map((vm) => (
                  <label key={vm.value} className="flex items-center gap-2 cursor-pointer">
                    <Checkbox
                      checked={enabledVerificationMethods.includes(vm.value)}
                      onCheckedChange={(checked) => {
                        const next = checked
                          ? [...enabledVerificationMethods, vm.value]
                          : enabledVerificationMethods.filter((v) => v !== vm.value);
                        // Ensure at least one method is always selected.
                        if (next.length > 0) {
                          onVerificationMethodsChange(next);
                        }
                      }}
                    />
                    <span className="text-sm">{vm.label}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          {/* Delivery channels (phone only) */}
          {deliveryChannels && onDeliveryChannelChange && (
            <div className="space-y-2">
              <Label className="text-sm font-medium text-muted-foreground">Delivery</Label>
              <RadioGroup
                value={activeDeliveryChannel}
                onValueChange={onDeliveryChannelChange}
                className="flex gap-4"
              >
                {deliveryChannels.map((dc) => (
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
          )}

          {/* Options checkboxes */}
          <div className="space-y-2">
            <Label className="text-sm font-medium text-muted-foreground">Options</Label>
            <div className="space-y-2">
              {onRequiredAtSignupChange !== undefined && (
                <label className="flex items-center gap-2 cursor-pointer">
                  <Checkbox
                    checked={requiredAtSignup}
                    onCheckedChange={(checked) => onRequiredAtSignupChange(!!checked)}
                  />
                  <span className="text-sm">Required at sign up</span>
                </label>
              )}
              {onVerifyAtSignupChange !== undefined && (
                <label className="flex items-center gap-2 cursor-pointer">
                  <Checkbox
                    checked={verifyAtSignup}
                    onCheckedChange={(checked) => onVerifyAtSignupChange(!!checked)}
                  />
                  <span className="text-sm">Verify at sign up</span>
                </label>
              )}
              {onAllowSignInChange !== undefined && (
                <label className="flex items-center gap-2 cursor-pointer">
                  <Checkbox
                    checked={allowSignIn}
                    onCheckedChange={(checked) => onAllowSignInChange(!!checked)}
                  />
                  <span className="text-sm">Allow to sign in</span>
                </label>
              )}
            </div>
          </div>

          {/* Settings inputs */}
          {(onCodeLengthChange || onExpiryChange) && (
            <div className="grid gap-4 sm:grid-cols-2">
              {onCodeLengthChange && (
                <Field>
                  <FieldLabel htmlFor={`${title}-code-length`}>Code Length</FieldLabel>
                  <Input
                    id={`${title}-code-length`}
                    type="number"
                    min={4}
                    max={8}
                    value={codeLength}
                    onChange={(e) => onCodeLengthChange(Number(e.target.value))}
                  />
                  <FieldDescription>Number of digits in the OTP code (4-8)</FieldDescription>
                </Field>
              )}
              {onExpiryChange && (
                <Field>
                  <FieldLabel htmlFor={`${title}-expiry`}>Expiry (seconds)</FieldLabel>
                  <Input
                    id={`${title}-expiry`}
                    type="number"
                    min={60}
                    max={3600}
                    value={expiry}
                    onChange={(e) => onExpiryChange(Number(e.target.value))}
                  />
                  <FieldDescription>How long the code/link remains valid (60-3600)</FieldDescription>
                </Field>
              )}
            </div>
          )}
        </CardContent>
      )}
    </Card>
  );
}
