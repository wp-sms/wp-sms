import { useState, useCallback } from 'preact/hooks';
import { PhoneInput as LitePhoneInput } from 'lite-phone-input/preact';
import { authConfig } from '../signals/config';

export function PhoneInput({ value = '', onChange, disabled, autoFocus = false }) {
    const [dropdownTarget, setDropdownTarget] = useState(null);
    const config = authConfig.value?.phone_input
        || window.wsmsMessagingButtonConfig?.phoneInput
        || {};

    const containerRef = useCallback((node) => {
        if (!node) return;
        const root = node.getRootNode();
        if (root instanceof ShadowRoot) {
            const mount = root.querySelector('#wsms-auth-popup')
                || root.querySelector('#wsms-auth-embed')
                || root.querySelector('#wsms-messaging-button');
            setDropdownTarget(mount || node);
        } else {
            setDropdownTarget(node);
        }
    }, []);

    return (
        <div ref={containerRef} className="wsms-phone-input">
            {dropdownTarget && (
                <LitePhoneInput
                    initialValue={value}
                    onChange={onChange}
                    disabled={disabled}
                    autoFocus={autoFocus}
                    defaultCountry={config.defaultCountry || 'US'}
                    preferredCountries={config.preferredCountries}
                    allowedCountries={config.allowedCountries}
                    excludedCountries={config.excludedCountries}
                    separateDialCode
                    dropdownContainer={dropdownTarget}
                />
            )}
        </div>
    );
}
