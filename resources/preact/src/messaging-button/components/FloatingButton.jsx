import { MessageIcon, CloseIcon } from './icons';

export function FloatingButton({ isOpen, config, onToggle }) {
    const position = config.position ?? 'bottom-right';
    const style = config.style ?? 'icon-text';
    const text = config.text ?? 'Chat with us';
    const primaryColor = config.primary_color ?? '#2563eb';
    const textColor = config.text_color ?? '#ffffff';
    const attention = config.attention ?? 'none';

    const positionClasses = position === 'bottom-left'
        ? 'wsms-mb-fab--left'
        : 'wsms-mb-fab--right';

    const attentionClass = !isOpen && attention !== 'none'
        ? `wsms-mb-fab--${attention}`
        : '';

    const showIcon = style !== 'text';
    const showText = style !== 'icon';

    return (
        <button
            type="button"
            class={`wsms-mb-fab ${positionClasses} ${attentionClass} ${!showIcon ? 'wsms-mb-fab--text-only' : ''}`}
            style={{
                '--fab-bg': primaryColor,
                '--fab-color': textColor,
            }}
            onClick={onToggle}
            aria-label={isOpen ? 'Close chat' : text}
            aria-expanded={isOpen}
        >
            {isOpen ? (
                <span class="wsms-mb-fab__icon wsms-mb-fab__icon--close">
                    <CloseIcon />
                </span>
            ) : (
                <>
                    {showIcon && (
                        <span class="wsms-mb-fab__icon">
                            <MessageIcon />
                        </span>
                    )}
                    {showText && (
                        <span class="wsms-mb-fab__text">{text}</span>
                    )}
                </>
            )}
        </button>
    );
}
