import { MethodCard } from '@/components/method-card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { MFA_FACTORS, toggleArrayItem } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface MfaFactorsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function MfaFactors({ settings, onUpdate }: MfaFactorsProps) {
  const factors = settings.mfa_factors;

  return (
    <div className="space-y-4">
      {MFA_FACTORS.map((factor) => (
        <MethodCard
          key={factor.id}
          title={factor.label}
          description={factor.description}
          icon={factor.icon}
          enabled={factors.includes(factor.id)}
          onToggle={(enabled) => onUpdate('mfa_factors', toggleArrayItem(factors, factor.id, enabled))}
        >
          {factor.id === 'backup_codes' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="backup_codes_count">Number of Codes</FieldLabel>
                <Input
                  id="backup_codes_count"
                  type="number"
                  min={4}
                  max={20}
                  value={settings.backup_codes_count}
                  onChange={(e) => onUpdate('backup_codes_count', Number(e.target.value))}
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
                  value={settings.backup_codes_length}
                  onChange={(e) => onUpdate('backup_codes_length', Number(e.target.value))}
                />
                <FieldDescription>Number of characters per code (6-12)</FieldDescription>
              </Field>
            </div>
          )}
        </MethodCard>
      ))}
    </div>
  );
}
