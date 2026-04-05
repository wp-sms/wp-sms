import { __ } from '@wordpress/i18n';
import { MessageCircle } from 'lucide-react';
import { DeepLinkEnroll } from './DeepLinkEnroll';

export function LineEnroll({ onEnroll, onEnrollmentComplete }) {
    return (
        <DeepLinkEnroll
            channelId="line"
            icon={MessageCircle}
            brandColor="var(--line)"
            label={__('Open in LINE', 'wp-sms')}
            onEnroll={onEnroll}
            onEnrollmentComplete={onEnrollmentComplete}
        />
    );
}
