import { useState, useRef } from 'preact/hooks';
import { forwardRef } from 'preact/compat';
import { __, sprintf } from '@wordpress/i18n';
import { Label } from './Label';
import { Input } from './Input';
import { PasswordInput } from './PasswordInput';

const VALIDATORS = {
    email: (v) => v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? __('Please enter a valid email address', 'wp-sms') : '',
    minLength: (min) => (v) => v && v.length < min ? sprintf(__('Must be at least %d characters', 'wp-sms'), min) : '',
    required: (v) => !v || !v.trim() ? __('This field is required', 'wp-sms') : '',
    match: (getOther, label) => (v) => v && v !== getOther() ? sprintf(__('%s do not match', 'wp-sms'), label) : '',
};

export const FormField = forwardRef(function FormField({
    label,
    id,
    type = 'text',
    validate,
    children,
    onBlur,
    onInput,
    ...inputProps
}, ref) {
    const [error, setError] = useState('');
    const touchedRef = useRef(false);

    function runValidation(value) {
        if (!validate) return;
        const msg = typeof validate === 'function'
            ? validate(value)
            : Array.isArray(validate)
                ? validate.reduce((err, fn) => err || fn(value), '')
                : '';
        setError(msg || '');
    }

    function handleBlur(e) {
        touchedRef.current = true;
        runValidation(e.target.value);
        onBlur?.(e);
    }

    function handleInput(e) {
        if (touchedRef.current) runValidation(e.target.value);
        onInput?.(e);
    }

    const showError = touchedRef.current && error;
    const InputComponent = type === 'password' ? PasswordInput : Input;
    const errorId = `${id}-error`;

    return (
        <div className="wsms-auth-stack-2">
            {label && <Label for={id}>{label}</Label>}
            {children || (
                <InputComponent
                    ref={ref}
                    id={id}
                    type={type === 'password' ? undefined : type}
                    aria-invalid={showError ? 'true' : undefined}
                    aria-describedby={showError ? errorId : undefined}
                    {...inputProps}
                    onBlur={handleBlur}
                    onInput={handleInput}
                />
            )}
            {showError && <span id={errorId} className="wsms-auth-field-error" role="alert">{error}</span>}
        </div>
    );
});

FormField.validators = VALIDATORS;
