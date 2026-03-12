import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { ShieldCheck, Clock, KeySquare } from 'lucide-react';
import { RoleMatrix } from '@/components/role-matrix';
import { ENROLLMENT_TIMING, toggleArrayItem } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface MfaPoliciesProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

export function MfaPolicies({ settings, onUpdate, roles }: MfaPoliciesProps) {
  function toggleRole(roleKey: string, enabled: boolean) {
    onUpdate('mfa_required_roles', toggleArrayItem(settings.mfa_required_roles, roleKey, enabled));
  }

  return (
    <div className="space-y-4">
      {/* Backup Codes */}
      <Card className={settings.backup_codes.enabled
        ? 'border-l-2 border-l-primary'
        : 'opacity-50'
      }>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <div className="space-y-1">
            <CardTitle className="flex items-center gap-2 text-base">
              <KeySquare className="h-4 w-4 text-muted-foreground" />
              Backup Codes
            </CardTitle>
            <CardDescription>Single-use recovery codes for MFA fallback</CardDescription>
          </div>
          <Switch
            checked={settings.backup_codes.enabled}
            onCheckedChange={(v) => onUpdate('backup_codes', { ...settings.backup_codes, enabled: v })}
            aria-label="Toggle Backup Codes"
          />
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
        <CardContent>
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
