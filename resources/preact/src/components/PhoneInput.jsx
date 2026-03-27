import { useState, useCallback, useMemo } from 'preact/hooks';
import { PhoneInput as LitePhoneInput } from '../adapters/LitePhoneInputAdapter';
import { authConfig } from '../signals/config';
import { createGeoIpLookup } from '../../../shared/geo-lookup';

export function PhoneInput({ value = '', onChange, disabled, autoFocus = false, config: configProp }) {
    const [dropdownTarget, setDropdownTarget] = useState(null);
    const config = configProp
        || authConfig.value?.phone_input
        || window.wsmsMessagingButtonConfig?.phoneInput
        || {};

    const restUrl = config.restUrl
        || window.wsmsAuth?.restUrl
        || window.wsmsMessagingButtonConfig?.restUrl;

    // Only use geoIpLookup when the backend couldn't detect country (e.g. cached page)
    const geoIpLookup = useMemo(
        () => config.hasGeoCountry ? undefined : createGeoIpLookup(restUrl),
        [config.hasGeoCountry, restUrl],
    );

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
                    separateDialCode={config.separateDialCode ?? false}
                    nationalMode={config.nationalMode ?? false}
                    allowDropdown={config.allowDropdown ?? true}
                    dropdownContainer={dropdownTarget}
                    geoIpLookup={geoIpLookup}
                />
            )}
        </div>
    );
}
