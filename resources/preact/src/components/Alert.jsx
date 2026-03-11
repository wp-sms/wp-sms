export function Alert({ type = 'error', message, onDismiss }) {
    if (!message) return null;
    return (
        <div class={`wsms-alert wsms-alert--${type}`} role="alert">
            <span>{message}</span>
            {onDismiss && (
                <button class="wsms-alert__close" onClick={onDismiss} aria-label="Dismiss" type="button">
                    &times;
                </button>
            )}
        </div>
    );
}
