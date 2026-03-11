const METHOD_META = {
    password:   { label: 'Password',   icon: '\u{1F512}' },
    phone_otp:  { label: 'Phone',      icon: '\u{1F4F1}' },
    email_otp:  { label: 'Email',      icon: '\u{2709}\u{FE0F}' },
    magic_link: { label: 'Magic Link', icon: '\u{2728}' },
};

export function MethodSelector({ methods, active, onChange }) {
    if (!methods || methods.length <= 1) return null;

    return (
        <div class="wsms-methods" role="tablist">
            {methods.map((method) => {
                const meta = METHOD_META[method];
                if (!meta) return null;
                return (
                    <button
                        key={method}
                        type="button"
                        role="tab"
                        aria-selected={active === method}
                        class={`wsms-method-tab ${active === method ? 'is-active' : ''}`}
                        onClick={() => onChange(method)}
                    >
                        <span class="wsms-method-tab__icon">{meta.icon}</span>
                        <span class="wsms-method-tab__label">{meta.label}</span>
                    </button>
                );
            })}
        </div>
    );
}
