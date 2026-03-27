/**
 * Thin Preact adapter for lite-phone-input.
 *
 * Reads the vanilla PhoneInput class from window.WsmsVendor (shared vendor bundle)
 * and wraps it as a Preact component with the same API as `lite-phone-input/preact`.
 */
import { h } from 'preact';
import { forwardRef } from 'preact/compat';
import { useEffect, useRef, useImperativeHandle } from 'preact/hooks';

const WIDGET_KEYS = new Set([
    'defaultCountry',
    'allowedCountries',
    'excludedCountries',
    'preferredCountries',
    'allowDropdown',
    'formatAsYouType',
    'strict',
    'separateDialCode',
    'nationalMode',
    'placeholder',
    'disabled',
    'locale',
    'renderFlag',
    'hiddenInput',
    'initialValue',
    'containerClass',
    'dropdownContainer',
    'geoIpLookup',
    'onChange',
    'onCountryChange',
    'onValidationChange',
    'onDropdownOpen',
    'onDropdownClose',
]);

const DYNAMIC_OPTION_KEYS = [
    'disabled',
    'allowedCountries',
    'excludedCountries',
    'preferredCountries',
    'allowDropdown',
    'formatAsYouType',
    'strict',
    'separateDialCode',
    'nationalMode',
    'placeholder',
    'locale',
    'renderFlag',
];

function extractInputAttrs(props) {
    const attrs = {};
    for (const [key, val] of Object.entries(props)) {
        if (!WIDGET_KEYS.has(key) && key !== 'class' && key !== 'className' && val !== undefined) {
            attrs[key] = String(val);
        }
    }
    return attrs;
}

export const PhoneInput = forwardRef(function PhoneInput(props, ref) {
    const containerRef = useRef(null);
    const instanceRef = useRef(null);
    const propsRef = useRef(props);
    const prevOptsRef = useRef({});
    propsRef.current = props;

    useEffect(() => {
        if (!containerRef.current) return;

        const VanillaPhoneInput = window.WsmsVendor.LitePhoneInput.PhoneInput;
        const p = propsRef.current;
        const inputAttributes = extractInputAttrs(p);

        const options = {
            defaultCountry: p.defaultCountry,
            allowedCountries: p.allowedCountries,
            excludedCountries: p.excludedCountries,
            preferredCountries: p.preferredCountries,
            allowDropdown: p.allowDropdown,
            formatAsYouType: p.formatAsYouType,
            strict: p.strict,
            separateDialCode: p.separateDialCode,
            nationalMode: p.nationalMode,
            placeholder: p.placeholder,
            disabled: p.disabled,
            locale: p.locale,
            renderFlag: p.renderFlag,
            hiddenInput: p.hiddenInput,
            containerClass: p.containerClass,
            dropdownContainer: p.dropdownContainer,
            geoIpLookup: p.geoIpLookup,
            initialValue: p.initialValue,
            inputAttributes,
            onChange: (e164, country, validation) => propsRef.current.onChange?.(e164, country, validation),
            onCountryChange: (country) => propsRef.current.onCountryChange?.(country),
            onValidationChange: (validation) => propsRef.current.onValidationChange?.(validation),
            onDropdownOpen: () => propsRef.current.onDropdownOpen?.(),
            onDropdownClose: () => propsRef.current.onDropdownClose?.(),
        };

        instanceRef.current = VanillaPhoneInput.mount(containerRef.current, options);

        return () => {
            instanceRef.current?.destroy();
            instanceRef.current = null;
        };
    }, []);

    useEffect(() => {
        if (!instanceRef.current) return;
        const instance = instanceRef.current;
        const opts = {};
        let hasChanges = false;

        for (const key of DYNAMIC_OPTION_KEYS) {
            if (props[key] !== undefined && props[key] !== prevOptsRef.current[key]) {
                opts[key] = props[key];
                hasChanges = true;
            }
        }

        const currentOpts = {};
        for (const key of DYNAMIC_OPTION_KEYS) {
            currentOpts[key] = props[key];
        }
        prevOptsRef.current = currentOpts;

        opts.inputAttributes = extractInputAttrs(props);
        if (hasChanges) {
            instance.setOptions(opts);
        }
    });

    useImperativeHandle(ref, () => ({
        getValue: () => instanceRef.current?.getValue() ?? '',
        getNationalNumber: () => instanceRef.current?.getNationalNumber() ?? '',
        getCountry: () => instanceRef.current?.getCountry() ?? { code: '', dialCode: '', name: '' },
        setValue: (e164) => instanceRef.current?.setValue(e164),
        setCountry: (code) => instanceRef.current?.setCountry(code),
        isValid: () => instanceRef.current?.isValid() ?? false,
        validate: () => instanceRef.current?.validate() ?? {
            valid: false,
            minDigits: 0,
            maxDigits: 0,
            currentDigits: 0,
        },
    }));

    return h('div', { ref: containerRef, class: props.class ?? props.className });
});
