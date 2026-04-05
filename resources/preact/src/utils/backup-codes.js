export function downloadCodes(codes) {
    const text = codes.join('\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'wsms-backup-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
}

export function copyCodes(codes) {
    return navigator.clipboard.writeText(codes.join('\n'));
}
