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
import { ShieldCheck, Clock } from 'lucide-react';
import { RoleMatrix } from '@/components/role-matrix';
import { ENROLLMENT_TIMING, toggleArrayItem } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface PoliciesProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  roles: Record<string, string>;
}

export function Policies({ settings, onUpdate, roles }: PoliciesProps) {
  function toggleRole(roleKey: string, enabled: boolean) {
    onUpdate('mfa_required_roles', toggleArrayItem(settings.mfa_required_roles, roleKey, enabled));
  }

  return (
    <div className="space-y-4">
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
                  onValueChange={(value) => onUpdate('enrollment_timing', value)}
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
