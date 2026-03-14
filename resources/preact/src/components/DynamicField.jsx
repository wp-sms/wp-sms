import { Input } from './ui/Input';
import { Label } from './ui/Label';

/**
 * Renders a form field based on its type definition.
 * Handles: text, textarea, select, checkbox.
 */
export function DynamicField({ field, value, onChange, disabled }) {
    const id = `wsms-field-${field.id}`;

    const helpText = field.description ? (
        <p className="text-xs text-muted-foreground">{field.description}</p>
    ) : null;

    if (field.type === 'checkbox') {
        return (
            <div className="space-y-1">
                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id={id}
                        checked={!!value}
                        onChange={(e) => onChange(e.target.checked)}
                        disabled={disabled}
                        className="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                    />
                    <Label for={id}>{field.label}{field.required && ' *'}</Label>
                </div>
                {helpText}
            </div>
        );
    }

    if (field.type === 'select') {
        return (
            <div className="space-y-2">
                <Label for={id}>{field.label}{field.required && ' *'}</Label>
                <select
                    id={id}
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    required={field.required}
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                >
                    <option value="">{field.placeholder || `Select ${field.label}...`}</option>
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
            <div className="space-y-2">
                <Label for={id}>{field.label}{field.required && ' *'}</Label>
                <textarea
                    id={id}
                    value={value || ''}
                    onInput={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    required={field.required}
                    placeholder={field.placeholder || ''}
                    rows={3}
                    className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                {helpText}
            </div>
        );
    }

    // Default: text
    return (
        <div className="space-y-2">
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
