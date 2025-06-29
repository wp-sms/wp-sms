// NOTE: Make sure to install 'react-intl-tel-input' and its types: npm install react-intl-tel-input
import React from 'react';
import PhoneInput from 'react-phone-input-2';
import 'react-phone-input-2/lib/style.css';

interface PhoneFieldProps {
  field: any;
  value: string;
  onChange: (value: string) => void;
  error?: string;
}

export function PhoneField({ field, value, onChange, error }: PhoneFieldProps) {
  return (
    <div className="space-y-2">
      <label htmlFor={field.key}>{field.label}</label>
      <PhoneInput
        country={'us'}
        value={value || ''}
        onChange={onChange}
        inputProps={{
          name: field.key,
          id: field.key,
          className: 'w-full',
        }}
        enableSearch
        enableAreaCodes
        enableAreaCodeStretch
        disableDropdown={false}
        countryCodeEditable={true}
        buttonStyle={{}}
      />
      {field.description && (
        <p className="text-xs text-muted-foreground">{field.description}</p>
      )}
      {error && (
        <p className="text-sm text-destructive mt-1">{error}</p>
      )}
    </div>
  );
}
