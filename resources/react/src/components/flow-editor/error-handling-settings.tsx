import { __, sprintf } from '@wordpress/i18n';
import { useState } from 'react';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ChevronDown, ChevronRight } from 'lucide-react';
import type { ErrorHandlingConfig } from '@/lib/api';

interface ErrorHandlingSettingsProps {
  value?: ErrorHandlingConfig;
  onChange: (value: ErrorHandlingConfig | undefined) => void;
}

export function ErrorHandlingSettings({ value, onChange }: ErrorHandlingSettingsProps) {
  const [open, setOpen] = useState(!!value && value.behavior !== 'stop');

  const config = value ?? { behavior: 'stop' as const };

  const handleBehaviorChange = (behavior: string) => {
    if (behavior === 'stop') {
      onChange(undefined);
      return;
    }
    onChange({
      behavior: behavior as ErrorHandlingConfig['behavior'],
      ...(behavior === 'retry' ? { maxRetries: 3, retryIntervalSecs: 30, continueOnExhausted: false } : {}),
    });
  };

  return (
    <div className="rounded-md border border-border/50">
      <button
        type="button"
        className="flex w-full items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
        onClick={() => setOpen(!open)}
      >
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {__('Error Handling', 'wp-sms')}
        {config.behavior !== 'stop' && (
          <span className="ms-auto text-xs font-normal text-muted-foreground/70">
            {config.behavior === 'continue' ? __('Continue on fail', 'wp-sms') : sprintf(__('Retry %dx', 'wp-sms'), config.maxRetries ?? 3)}
          </span>
        )}
      </button>

      {open && (
        <div className="space-y-3 border-t border-border/50 px-4 py-3">
          <Field>
            <FieldLabel htmlFor="error-behavior">{__('On failure', 'wp-sms')}</FieldLabel>
            <Select value={config.behavior} onValueChange={handleBehaviorChange}>
              <SelectTrigger id="error-behavior">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="stop">{__('Stop flow', 'wp-sms')}</SelectItem>
                <SelectItem value="continue">{__('Continue', 'wp-sms')}</SelectItem>
                <SelectItem value="retry">{__('Retry', 'wp-sms')}</SelectItem>
              </SelectContent>
            </Select>
            <FieldDescription>
              {config.behavior === 'stop' && __('The flow will stop if this action fails.', 'wp-sms')}
              {config.behavior === 'continue' && __('The flow will continue to the next step even if this action fails.', 'wp-sms')}
              {config.behavior === 'retry' && __('The action will be retried before giving up.', 'wp-sms')}
            </FieldDescription>
          </Field>

          {config.behavior === 'retry' && (
            <>
              <Field>
                <FieldLabel htmlFor="max-retries">{__('Max retries', 'wp-sms')}</FieldLabel>
                <Input
                  id="max-retries"
                  type="number"
                  min={1}
                  max={10}
                  value={config.maxRetries ?? 3}
                  onChange={(e) => onChange({ ...config, maxRetries: Math.max(1, Math.min(10, Number(e.target.value) || 1)) })}
                />
              </Field>

              <Field>
                <FieldLabel htmlFor="retry-interval">{__('Retry interval (seconds)', 'wp-sms')}</FieldLabel>
                <Input
                  id="retry-interval"
                  type="number"
                  min={1}
                  max={3600}
                  value={config.retryIntervalSecs ?? 30}
                  onChange={(e) => onChange({ ...config, retryIntervalSecs: Math.max(1, Number(e.target.value) || 1) })}
                />
              </Field>

              <Field orientation="horizontal">
                <FieldLabel htmlFor="continue-on-exhausted">{__('Continue after retries exhausted', 'wp-sms')}</FieldLabel>
                <Switch
                  id="continue-on-exhausted"
                  checked={config.continueOnExhausted ?? false}
                  onCheckedChange={(v) => onChange({ ...config, continueOnExhausted: v })}
                />
              </Field>
            </>
          )}
        </div>
      )}
    </div>
  );
}
