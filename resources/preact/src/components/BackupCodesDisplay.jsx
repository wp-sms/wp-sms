export function BackupCodesDisplay({ codes, onDismiss }) {
    if (!codes || codes.length === 0) return null;

    function handleDownload() {
        const text = codes.join('\n');
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'wsms-backup-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    }

    function handleCopy() {
        navigator.clipboard.writeText(codes.join('\n'));
    }

    return (
        <div class="wsms-backup-codes">
            <div class="wsms-backup-codes__header">
                <h2 class="wsms-section-title">Save Your Backup Codes</h2>
                <p class="wsms-text-secondary">
                    Store these codes in a safe place. Each code can only be used once.
                    You won't be able to see them again.
                </p>
            </div>

            <div class="wsms-backup-codes__grid">
                {codes.map((code, i) => (
                    <code key={i} class="wsms-backup-codes__code">{code}</code>
                ))}
            </div>

            <div class="wsms-backup-codes__actions">
                <button type="button" class="wsms-btn wsms-btn--secondary wsms-btn--sm" onClick={handleDownload}>
                    Download
                </button>
                <button type="button" class="wsms-btn wsms-btn--secondary wsms-btn--sm" onClick={handleCopy}>
                    Copy All
                </button>
                <button type="button" class="wsms-btn wsms-btn--text wsms-btn--sm" onClick={onDismiss}>
                    I've saved them
                </button>
            </div>
        </div>
    );
}
