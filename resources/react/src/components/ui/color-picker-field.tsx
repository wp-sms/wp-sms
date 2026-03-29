import { useId } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';

interface ColorPickerFieldProps {
  id?: string;
  label: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
}

export function ColorPickerField({ id, label, value, placeholder, onChange }: ColorPickerFieldProps) {
  const autoId = useId();
  const inputId = id ?? autoId;

  return (
    <Field>
      <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
      <div className="flex items-center gap-2">
        <input
          type="color"
          value={value || '#000000'}
          onChange={(e) => onChange(e.target.value)}
          aria-label={sprintf(__('Pick %s color', 'wp-sms'), label)}
          className="h-6 w-6 shrink-0 cursor-pointer rounded border-2 border-input p-0 transition-colors hover:ring-2 hover:ring-ring/20 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded [&::-webkit-color-swatch]:border-0 [&::-moz-color-swatch]:rounded [&::-moz-color-swatch]:border-0"
        />
        <Input
          id={inputId}
          dir="ltr"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="font-mono"
        />
      </div>
    </Field>
  );
}
