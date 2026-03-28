import { __, sprintf } from '@wordpress/i18n';
import { Input } from './ui/Input';
import { Label } from './ui/Label';

/**
 * Renders a form field based on its type definition.
 * Handles: text, textarea, select, checkbox.
 */
export function DynamicField({ field, value, onChange, disabled }) {
    const id = `wsms-field-${field.id}`;

    const helpText = field.description ? (
        <p className="wsms-auth-text-xs wsms-auth-text-muted">{field.description}</p>
    ) : null;

    if (field.type === 'checkbox') {
        return (
            <div className="wsms-auth-stack-1">
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <input
                        type="checkbox"
                        id={id}
                        checked={!!value}
                        onChange={(e) => onChange(e.target.checked)}
                        disabled={disabled}
                        className="wsms-auth-checkbox"
                    />
                    <Label for={id}>{field.label}{field.required && ' *'}</Label>
                </div>
                {helpText}
            </div>
        );
    }

    if (field.type === 'select') {
        return (
            <div className="wsms-auth-stack-2">
                <Label for={id}>{field.label}{field.required && ' *'}</Label>
                <select
                    id={id}
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    required={field.required}
                    className="wsms-auth-select"
                >
                    <option value="">{field.placeholder || sprintf(__('Select %s...', 'wp-sms'), field.label)}</option>
                    {(field.options || []).map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
                {helpText}
            </div>
        );
    }

    if (field.type === 'textarea') {
        return (
            <div className="wsms-auth-stack-2">
                <Label for={id}>{field.label}{field.required && ' *'}</Label>
                <textarea
                    id={id}
                    value={value || ''}
                    onInput={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    required={field.required}
                    placeholder={field.placeholder || ''}
                    rows={3}
                    className="wsms-auth-textarea"
                />
                {helpText}
            </div>
        );
    }

    // Default: text
    return (
        <div className="wsms-auth-stack-2">
            <Label for={id}>{field.label}{field.required && ' *'}</Label>
            <Input
                id={id}
                type="text"
                value={value || ''}
                onInput={(e) => onChange(e.target.value)}
                disabled={disabled}
                required={field.required}
                placeholder={field.placeholder || ''}
            />
            {helpText}
        </div>
    );
}
