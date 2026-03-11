import { useState, useRef } from 'preact/hooks';

const COUNTRIES = [
    { code: 'US', dial: '1',  flag: '\u{1F1FA}\u{1F1F8}' },
    { code: 'GB', dial: '44', flag: '\u{1F1EC}\u{1F1E7}' },
    { code: 'CA', dial: '1',  flag: '\u{1F1E8}\u{1F1E6}' },
    { code: 'AU', dial: '61', flag: '\u{1F1E6}\u{1F1FA}' },
    { code: 'DE', dial: '49', flag: '\u{1F1E9}\u{1F1EA}' },
    { code: 'FR', dial: '33', flag: '\u{1F1EB}\u{1F1F7}' },
    { code: 'IT', dial: '39', flag: '\u{1F1EE}\u{1F1F9}' },
    { code: 'ES', dial: '34', flag: '\u{1F1EA}\u{1F1F8}' },
    { code: 'NL', dial: '31', flag: '\u{1F1F3}\u{1F1F1}' },
    { code: 'BR', dial: '55', flag: '\u{1F1E7}\u{1F1F7}' },
    { code: 'IN', dial: '91', flag: '\u{1F1EE}\u{1F1F3}' },
    { code: 'CN', dial: '86', flag: '\u{1F1E8}\u{1F1F3}' },
    { code: 'JP', dial: '81', flag: '\u{1F1EF}\u{1F1F5}' },
    { code: 'KR', dial: '82', flag: '\u{1F1F0}\u{1F1F7}' },
    { code: 'MX', dial: '52', flag: '\u{1F1F2}\u{1F1FD}' },
    { code: 'RU', dial: '7',  flag: '\u{1F1F7}\u{1F1FA}' },
    { code: 'ZA', dial: '27', flag: '\u{1F1FF}\u{1F1E6}' },
    { code: 'NG', dial: '234', flag: '\u{1F1F3}\u{1F1EC}' },
    { code: 'EG', dial: '20', flag: '\u{1F1EA}\u{1F1EC}' },
    { code: 'TR', dial: '90', flag: '\u{1F1F9}\u{1F1F7}' },
    { code: 'SA', dial: '966', flag: '\u{1F1F8}\u{1F1E6}' },
    { code: 'AE', dial: '971', flag: '\u{1F1E6}\u{1F1EA}' },
    { code: 'PK', dial: '92', flag: '\u{1F1F5}\u{1F1F0}' },
    { code: 'ID', dial: '62', flag: '\u{1F1EE}\u{1F1E9}' },
    { code: 'PH', dial: '63', flag: '\u{1F1F5}\u{1F1ED}' },
    { code: 'TH', dial: '66', flag: '\u{1F1F9}\u{1F1ED}' },
    { code: 'SE', dial: '46', flag: '\u{1F1F8}\u{1F1EA}' },
    { code: 'NO', dial: '47', flag: '\u{1F1F3}\u{1F1F4}' },
    { code: 'PL', dial: '48', flag: '\u{1F1F5}\u{1F1F1}' },
    { code: 'IR', dial: '98', flag: '\u{1F1EE}\u{1F1F7}' },
];

export function PhoneInput({ value = '', onChange, disabled }) {
    const [country, setCountry] = useState(COUNTRIES[0]);
    const inputRef = useRef(null);

    function handleCountryChange(e) {
        const selected = COUNTRIES.find((c) => c.code === e.target.value);
        if (selected) {
            setCountry(selected);
            const number = inputRef.current?.value || '';
            onChange(`+${selected.dial}${number.replace(/\D/g, '')}`);
        }
    }

    function handleNumberChange(e) {
        const number = e.target.value.replace(/\D/g, '');
        onChange(`+${country.dial}${number}`);
    }

    // Extract the number part from value if it starts with the dial code
    const numberPart = value.startsWith(`+${country.dial}`)
        ? value.slice(country.dial.length + 1)
        : '';

    return (
        <div class="wsms-phone-input">
            <select
                class="wsms-phone-input__country"
                value={country.code}
                onChange={handleCountryChange}
                disabled={disabled}
                aria-label="Country code"
            >
                {COUNTRIES.map((c) => (
                    <option key={c.code} value={c.code}>
                        {c.flag} +{c.dial}
                    </option>
                ))}
            </select>
            <input
                ref={inputRef}
                type="tel"
                class="wsms-input wsms-phone-input__number"
                value={numberPart}
                onInput={handleNumberChange}
                placeholder="Phone number"
                disabled={disabled}
                inputMode="numeric"
            />
        </div>
    );
}
