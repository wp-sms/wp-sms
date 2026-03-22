import { useState } from 'react';
import { PhoneInput as LitePhoneInput } from 'lite-phone-input/react';

const globalConfig = (window as any).wpSmsSettings?.phoneInput ?? {};

interface PhoneInputProps {
  value: string;
  onChange: (e164: string) => void;
  disabled?: boolean;
}

export function PhoneInput({ value, onChange, disabled }: PhoneInputProps) {
  const [container, setContainer] = useState<HTMLDivElement | null>(null);

  return (
    <div ref={setContainer} className="wsms-phone-input">
      {container && (
        <LitePhoneInput
          initialValue={value}
          onChange={onChange}
          disabled={disabled}
          defaultCountry={globalConfig.defaultCountry || 'US'}
          preferredCountries={globalConfig.preferredCountries}
          allowedCountries={globalConfig.allowedCountries}
          excludedCountries={globalConfig.excludedCountries}
          dropdownContainer={container}
        />
      )}
    </div>
  );
}
