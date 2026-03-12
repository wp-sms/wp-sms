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
        <Card className="mb-4 border-info/30 bg-info/5">
            <CardHeader>
                <CardTitle className="text-base">Save Your Backup Codes</CardTitle>
                <CardDescription>
                    Store these codes in a safe place. Each code can only be used once.
                    You won't be able to see them again.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-1.5">
                    {codes.map((code, i) => (
                        <code
                            key={i}
                            className="block rounded border bg-card px-2.5 py-1.5 text-center font-mono text-sm tracking-wider"
                        >
                            {code}
                        </code>
                    ))}
                </div>
            </CardContent>
            <CardFooter className="gap-2">
                <Button variant="outline" size="sm" onClick={handleDownload}>
                    Download
                </Button>
                <Button variant="outline" size="sm" onClick={handleCopy}>
                    Copy All
                </Button>
                <Button variant="link" size="sm" onClick={onDismiss}>
                    I've saved them
                </Button>
            </CardFooter>
        </Card>
    );
}
