import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ShieldCheck, Clock, KeySquare, Monitor, TriangleAlert, Info } from 'lucide-react';
import { RoleMatrix } from '@/components/role-matrix';
import { ENROLLMENT_TIMING, TRUSTED_DEVICE_TTL_OPTIONS, toggleArrayItem } from '@/lib/constants';
import { getConfig } from '@/lib/api';
import type { AuthSettings } from '@/lib/api';

interface MfaPoliciesProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

function hasMfaChannelsEnabled(s: Required<AuthSettings>): boolean {
  return !!s.totp?.enabled
    || !!s.passkey?.enabled
    || !!s.backup_codes?.enabled
    || (!!s.email?.enabled && s.email?.usage === 'mfa')
    || (!!s.phone?.enabled && s.phone?.usage === 'mfa')
    || !!s.telegram?.enabled;
}

export function MfaPolicies({ settings, onUpdate, roles }: MfaPoliciesProps) {
  const { currentUserHasMfa, currentUserRoles } = getConfig();
  const hasRolesSelected = settings.mfa_required_roles.length > 0;
  const ownRoleSelected = hasRolesSelected && currentUserRoles.some((r) => settings.mfa_required_roles.includes(r));
  const showOwnRoleWarning = ownRoleSelected && !currentUserHasMfa;
  const showNoChannelsWarning = hasRolesSelected && !hasMfaChannelsEnabled(settings);
  const showImmediateNote = hasRolesSelected && settings.enrollment_timing === 'on_registration';

  function toggleRole(roleKey: string, enabled: boolean) {
    onUpdate('mfa_required_roles', toggleArrayItem(settings.mfa_required_roles, roleKey, enabled));
  }

  return (
    <div className="space-y-4">
      {/* Backup Codes */}
      <Card active={settings.backup_codes.enabled}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <KeySquare className="h-4 w-4 text-muted-foreground" />
            Backup Codes
          </CardTitle>
          <CardDescription>Single-use recovery codes for MFA fallback</CardDescription>
          <CardAction>
            <Switch
              checked={settings.backup_codes.enabled}
              onCheckedChange={(v) => onUpdate('backup_codes', { ...settings.backup_codes, enabled: v })}
              aria-label="Toggle Backup Codes"
            />
          </CardAction>
        </CardHeader>
        {settings.backup_codes.enabled && (
          <CardContent className="border-t pt-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="backup_codes_count">Number of Codes</FieldLabel>
                <Input
                  id="backup_codes_count"
                  type="number"
                  min={4}
                  max={20}
                  value={settings.backup_codes.count}
                  onChange={(e) => onUpdate('backup_codes', { ...settings.backup_codes, count: Number(e.target.value) })}
                />
                <FieldDescription>How many backup codes to generate (4-20)</FieldDescription>
              </Field>
              <Field>
                <FieldLabel htmlFor="backup_codes_length">Code Length</FieldLabel>
                <Input
                  id="backup_codes_length"
                  type="number"
                  min={6}
                  max={12}
                  value={settings.backup_codes.length}
                  onChange={(e) => onUpdate('backup_codes', { ...settings.backup_codes, length: Number(e.target.value) })}
                />
                <FieldDescription>Number of characters per code (6-12)</FieldDescription>
              </Field>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Trusted Devices */}
      <Card active={settings.trusted_devices.enabled}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Monitor className="h-4 w-4 text-muted-foreground" />
            Trusted Devices
          </CardTitle>
          <CardDescription>Allow users to skip MFA on recognized browsers</CardDescription>
          <CardAction>
            <Switch
              checked={settings.trusted_devices.enabled}
              onCheckedChange={(v) => onUpdate('trusted_devices', { ...settings.trusted_devices, enabled: v })}
              aria-label="Toggle Trusted Devices"
            />
          </CardAction>
        </CardHeader>
        {settings.trusted_devices.enabled && (
          <CardContent className="border-t pt-4">
            <div className="max-w-sm">
              <Field>
                <FieldLabel htmlFor="trusted_device_ttl">Trust Duration</FieldLabel>
                <Select
                  value={String(settings.trusted_devices.ttl)}
                  onValueChange={(v) => onUpdate('trusted_devices', { ...settings.trusted_devices, ttl: Number(v) })}
                >
                  <SelectTrigger id="trusted_device_ttl">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {TRUSTED_DEVICE_TTL_OPTIONS.map((opt) => (
                      <SelectItem key={opt.value} value={String(opt.value)}>
                        {opt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FieldDescription>How long a device stays trusted before MFA is required again</FieldDescription>
              </Field>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Required Roles */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <ShieldCheck className="h-4 w-4 text-muted-foreground" />
            Required Roles
          </CardTitle>
          <CardDescription>
            Select which WordPress roles must use multi-factor authentication
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {showOwnRoleWarning && (
            <Alert variant="destructive">
              <TriangleAlert className="h-4 w-4" />
              <AlertDescription>
                You must enroll in MFA before requiring it for your role. Go to your account Security page to set up MFA first.
              </AlertDescription>
            </Alert>
          )}
          {showNoChannelsWarning && (
            <Alert>
              <TriangleAlert className="h-4 w-4" />
              <AlertDescription>
                No MFA-capable channels are enabled. Users won't be able to enroll until you enable at least one MFA channel (e.g. TOTP, Passkey, or a channel set to MFA usage).
              </AlertDescription>
            </Alert>
          )}
          <RoleMatrix
            roles={roles}
            selectedRoles={settings.mfa_required_roles}
            onToggleRole={toggleRole}
          />
        </CardContent>
      </Card>

      {/* Enrollment Timing */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Clock className="h-4 w-4 text-muted-foreground" />
            Enrollment Timing
          </CardTitle>
          <CardDescription>
            Control when users are required to set up MFA
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="max-w-sm">
              <Field>
                <FieldLabel htmlFor="enrollment_timing">Timing</FieldLabel>
                <Select
                  value={settings.enrollment_timing}
                  onValueChange={(value) => onUpdate('enrollment_timing', value as AuthSettings['enrollment_timing'])}
                >
                  <SelectTrigger id="enrollment_timing">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ENROLLMENT_TIMING.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>
                        {opt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FieldDescription>
                  {ENROLLMENT_TIMING.find((t) => t.value === settings.enrollment_timing)?.description}
                </FieldDescription>
              </Field>
            </div>

            {showImmediateNote && (
              <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                  Users with the selected roles will be required to set up MFA immediately upon their next login.
                </AlertDescription>
              </Alert>
            )}

            {settings.enrollment_timing === 'grace_period' && (
              <div className="max-w-xs">
                <Field>
                  <FieldLabel htmlFor="grace_period_days">Grace Period (days)</FieldLabel>
                  <Input
                    id="grace_period_days"
                    type="number"
                    min={1}
                    max={90}
                    value={settings.grace_period_days}
                    onChange={(e) => onUpdate('grace_period_days', Number(e.target.value))}
                  />
                  <FieldDescription>Number of days before MFA is required (1-90)</FieldDescription>
                </Field>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
