import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from './ui/Card';
import { Button } from './ui/Button';

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
        <Card className="wsms-auth-mb-4 wsms-auth-backup-codes">
            <CardHeader>
                <CardTitle className="wsms-auth-text-base">Save Your Backup Codes</CardTitle>
                <CardDescription>
                    Store these codes in a safe place. Each code can only be used once.
                    You won't be able to see them again.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="wsms-auth-backup-codes-grid">
                    {codes.map((code, i) => (
                        <code key={i} className="wsms-auth-backup-code">{code}</code>
                    ))}
                </div>
            </CardContent>
            <CardFooter>
                <Button variant="outline" size="sm" onClick={handleDownload}>Download</Button>
                <Button variant="outline" size="sm" onClick={handleCopy}>Copy All</Button>
                <Button variant="link" size="sm" onClick={onDismiss}>I've saved them</Button>
            </CardFooter>
        </Card>
    );
}
