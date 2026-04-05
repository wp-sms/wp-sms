import { __ } from '@wordpress/i18n';
import { Send } from 'lucide-react';
import { DeepLinkEnroll } from './DeepLinkEnroll';

export function TelegramEnroll({ onEnroll, onEnrollmentComplete }) {
    return (
        <DeepLinkEnroll
            channelId="telegram"
            icon={Send}
            brandColor="var(--telegram)"
            label={__('Open in Telegram', 'wp-sms')}
            onEnroll={onEnroll}
            onEnrollmentComplete={onEnrollmentComplete}
        />
    );
}
