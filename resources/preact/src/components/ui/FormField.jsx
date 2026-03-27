import { useState, useRef } from 'preact/hooks';
import { forwardRef } from 'preact/compat';
import { Label } from './Label';
import { Input } from './Input';
import { PasswordInput } from './PasswordInput';

const VALIDATORS = {
    email: (v) => v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'Please enter a valid email address' : '',
    minLength: (min) => (v) => v && v.length < min ? `Must be at least ${min} characters` : '',
    required: (v) => !v || !v.trim() ? 'This field is required' : '',
    match: (getOther, label) => (v) => v && v !== getOther() ? `${label} do not match` : '',
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

    return (
        <div className="wsms-auth-stack-2">
            {label && <Label for={id}>{label}</Label>}
            {children || (
                <InputComponent
                    ref={ref}
                    id={id}
                    type={type === 'password' ? undefined : type}
                    aria-invalid={showError ? 'true' : undefined}
                    {...inputProps}
                    onBlur={handleBlur}
                    onInput={handleInput}
                />
            )}
            {showError && <span className="wsms-auth-field-error">{error}</span>}
        </div>
    );
});

FormField.validators = VALIDATORS;
