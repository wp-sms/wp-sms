import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from './ui/Card';
import { Button } from './ui/Button';
import { downloadCodes, copyCodes } from '../utils/backup-codes';

export function BackupCodesDisplay({ codes, onDismiss }) {
    if (!codes || codes.length === 0) return null;

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
                <Button variant="outline" size="sm" onClick={() => downloadCodes(codes)}>{__('Download', 'wp-sms')}</Button>
                <Button variant="outline" size="sm" onClick={() => copyCodes(codes)}>{__('Copy All', 'wp-sms')}</Button>
                <Button variant="link" size="sm" onClick={onDismiss}>{__('I\'ve saved them', 'wp-sms')}</Button>
            </CardFooter>
        </Card>
    );
}
