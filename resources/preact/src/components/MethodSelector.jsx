import { cn } from '@/utils/cn';

const METHOD_META = {
    password:   { label: 'Password',   icon: '\u{1F512}' },
    phone:      { label: 'Phone',      icon: '\u{1F4F1}' },
    email:      { label: 'Email',      icon: '\u{2709}\u{FE0F}' },
};

export function MethodSelector({ methods, active, onChange }) {
    if (!methods || methods.length <= 1) return null;

    return (
        <div className="flex flex-wrap gap-2 mb-4" role="tablist">
            {methods.map((method) => {
                const meta = METHOD_META[method];
                if (!meta) return null;
                const isActive = active === method;
                return (
                    <button
                        key={method}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors cursor-pointer bg-transparent',
                            isActive
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground hover:border-primary hover:text-primary',
                        )}
                        onClick={() => onChange(method)}
                    >
                        <span className="text-sm leading-none">{meta.icon}</span>
                        <span>{meta.label}</span>
                    </button>
                );
            })}
        </div>
    );
}
