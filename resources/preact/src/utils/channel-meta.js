import { __ } from '@wordpress/i18n';
import { Smartphone, Mail, ClipboardList, Lock, Send, KeyRound, Fingerprint, MessageCircle } from 'lucide-react';

export const CHANNEL_META = {
    phone:        { icon: Smartphone,    label: __('Phone', 'wp-sms'),             description: __('Receive a code via text message', 'wp-sms') },
    email:        { icon: Mail,          label: __('Email', 'wp-sms'),             description: __('Receive a code via email', 'wp-sms') },
    telegram:     { icon: Send,          label: __('Telegram', 'wp-sms'),          description: __('Receive a code via Telegram bot', 'wp-sms') },
    line:         { icon: MessageCircle,  label: __('LINE', 'wp-sms'),              description: __('Receive a code via LINE', 'wp-sms') },
    totp:         { icon: KeyRound,      label: __('Authenticator App', 'wp-sms'), description: __('Use an app like Google Authenticator or Authy', 'wp-sms') },
    passkey:      { icon: Fingerprint,   label: __('Passkey', 'wp-sms'),           description: __('Use fingerprint, face, or security key', 'wp-sms') },
    backup_codes: { icon: ClipboardList, label: __('Backup Codes', 'wp-sms'),      description: __('One-time use recovery codes', 'wp-sms') },
};

export function getChannelMeta(channelId, method = {}) {
    const hardcoded = CHANNEL_META[channelId];
    return {
        label: hardcoded?.label ?? method.name ?? channelId,
        icon: hardcoded?.icon ?? (method.icon_svg ? null : Lock),
        iconSvg: hardcoded ? null : (method.icon_svg ?? null),
        description: hardcoded?.description ?? method.description ?? '',
    };
}
