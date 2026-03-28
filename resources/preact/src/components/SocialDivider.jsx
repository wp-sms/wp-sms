import { __ } from '@wordpress/i18n';
import { Separator } from './ui/Separator';

export function SocialDivider() {
    return (
        <div className="wsms-auth-social-divider">
            <Separator />
            <span className="wsms-auth-social-divider__text">{__('or', 'wp-sms')}</span>
        </div>
    );
}
