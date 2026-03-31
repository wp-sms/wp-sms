import { __ } from '@wordpress/i18n';

export const CHANNEL_LABELS: Record<string, string> = {
  sms: __('SMS', 'wp-sms'),
  email: __('Email', 'wp-sms'),
  whatsapp: __('WhatsApp', 'wp-sms'),
  telegram: __('Telegram', 'wp-sms'),
  viber: __('Viber', 'wp-sms'),
  voice: __('Voice', 'wp-sms'),
  line: __('LINE', 'wp-sms'),
};

export function channelLabel(channel: string): string {
  return CHANNEL_LABELS[channel] ?? channel.charAt(0).toUpperCase() + channel.slice(1);
}

export const CHANNEL_BADGE_VARIANT: Record<string, 'info' | 'purple' | 'success' | 'neutral'> = {
  sms: 'info',
  email: 'purple',
  whatsapp: 'success',
  telegram: 'info',
  line: 'neutral',
};

export function channelBadgeVariant(channel: string): 'info' | 'purple' | 'success' | 'neutral' {
  return CHANNEL_BADGE_VARIANT[channel] ?? 'neutral';
}
