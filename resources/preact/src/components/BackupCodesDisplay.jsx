import { __ } from '@wordpress/i18n';
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
                <CardTitle className="wsms-auth-text-base">{__('Save Your Backup Codes', 'wp-sms')}</CardTitle>
                <CardDescription>
                    {__('Store these codes in a safe place. Each code can only be used once. You won\'t be able to see them again.', 'wp-sms')}
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
                <Button variant="outline" size="sm" onClick={handleDownload}>{__('Download', 'wp-sms')}</Button>
                <Button variant="outline" size="sm" onClick={handleCopy}>{__('Copy All', 'wp-sms')}</Button>
                <Button variant="link" size="sm" onClick={onDismiss}>{__('I\'ve saved them', 'wp-sms')}</Button>
            </CardFooter>
        </Card>
    );
}
