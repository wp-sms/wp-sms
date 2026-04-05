import { __ } from '@wordpress/i18n';
import { legalLinks } from '../signals/config';

/**
 * Legal links (Terms of Service / Privacy Policy).
 *
 * @param {'consent'|'footer'} variant
 *   - consent: "By creating an account, you agree to our Terms/Privacy." (registration forms)
 *   - footer: Just the links (login page footer)
 */
export function LegalLinks({ variant = 'footer' }) {
    const legal = legalLinks.value;
    if (!legal || (!legal.terms_url && !legal.privacy_url)) return null;

    const links = (
        <>
            {legal.terms_url && (
                <a href={legal.terms_url} target="_blank" rel="noopener noreferrer" className="wsms-auth-legal-link">
                    {__('Terms of Service', 'wp-sms')}
                </a>
            )}
            {legal.terms_url && legal.privacy_url && ` ${__('and', 'wp-sms')} `}
            {legal.privacy_url && (
                <a href={legal.privacy_url} target="_blank" rel="noopener noreferrer" className="wsms-auth-legal-link">
                    {__('Privacy Policy', 'wp-sms')}
                </a>
            )}
        </>
    );

    if (variant === 'consent') {
        return (
            <p className="wsms-auth-center wsms-auth-text-xs wsms-auth-text-muted">
                {__('By creating an account, you agree to our', 'wp-sms')}{' '}
                {links}.
            </p>
        );
    }

    // footer variant
    return (
        <p className="wsms-auth-center wsms-auth-text-xs wsms-auth-text-muted wsms-auth-mt-1">
            {links}
        </p>
    );
}
