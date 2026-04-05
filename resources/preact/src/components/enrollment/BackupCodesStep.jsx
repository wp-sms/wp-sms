import { __ } from '@wordpress/i18n';
import { Copy, Download, Printer } from 'lucide-react';
import { Button } from '../ui/Button';
import { backupCodes, codesSaved } from '../../signals/enrollment';
import { downloadCodes, copyCodes } from '../../utils/backup-codes';

export function BackupCodesStep({ onContinue }) {
    const codes = backupCodes.value || [];
    const saved = codesSaved.value;

    return (
        <div className="wsms-auth-stack-4">
            <div className="wsms-auth-center wsms-auth-stack-2">
                <h2 className="wsms-auth-text-xl wsms-auth-font-semibold">
                    {__('Save Your Backup Codes', 'wp-sms')}
                </h2>
                <p className="wsms-auth-text-sm wsms-auth-text-muted">
                    {__("Store these codes in a safe place. Each code can only be used once. You won't be able to see them again.", 'wp-sms')}
                </p>
            </div>

            <div className="wsms-auth-backup-codes-grid wsms-auth-wizard-backup-codes" id="wsms-backup-codes-print">
                {codes.map((code, i) => (
                    <code key={i} className="wsms-auth-backup-code">{code}</code>
                ))}
            </div>

            <div className="wsms-auth-flex-wrap">
                <Button variant="outline" size="sm" onClick={() => window.print()}>
                    <Printer size={14} />
                    {__('Print', 'wp-sms')}
                </Button>
                <Button variant="outline" size="sm" onClick={() => downloadCodes(codes)}>
                    <Download size={14} />
                    {__('Download', 'wp-sms')}
                </Button>
                <Button variant="outline" size="sm" onClick={() => copyCodes(codes)}>
                    <Copy size={14} />
                    {__('Copy All', 'wp-sms')}
                </Button>
            </div>

            <label className="wsms-auth-wizard-checkbox">
                <input
                    type="checkbox"
                    checked={saved}
                    onChange={(e) => { codesSaved.value = e.target.checked; }}
                    className="wsms-auth-wizard-check"
                />
                <span className="wsms-auth-text-sm">
                    {__("I've saved these backup codes in a secure location", 'wp-sms')}
                </span>
            </label>

            <Button className="wsms-auth-full" onClick={onContinue} disabled={!saved}>
                {__('Continue', 'wp-sms')}
            </Button>
        </div>
    );
}
