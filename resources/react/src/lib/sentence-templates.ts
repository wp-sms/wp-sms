import { __ } from '@wordpress/i18n';

export interface SentenceTemplate {
  /** Each line is rendered as a sentence row. {field} becomes a token. */
  lines: string[];
  /** Fields rendered in collapsible "Settings" instead of inline. */
  advancedFields: string[];
}

export const SENTENCE_TEMPLATES: Record<string, SentenceTemplate> = {
  send_message: {
    lines: [
      __('Send {channel} to {recipient_mode} {to} {tag_id} {list_id}', 'wp-sms'),
      __('{message_mode}: {body} {provider_template_id}', 'wp-sms'),
    ],
    advancedFields: ['gateway', 'subject', 'media_url', 'template_variables'],
  },
  http_request: {
    lines: [__('Make a {method} request to {url}', 'wp-sms'), __('Body: {body}', 'wp-sms')],
    advancedFields: ['headers'],
  },
  subscribe_to_list: {
    lines: [__('Subscribe {contact_id} to list {list_id}', 'wp-sms')],
    advancedFields: [],
  },
  unsubscribe_from_list: {
    lines: [__('Unsubscribe {contact_id} from list {list_id}', 'wp-sms')],
    advancedFields: [],
  },
};

/** Extract {field} references from a sentence line. */
export function extractTokenFields(line: string): string[] {
  const matches = line.match(/\{(\w+)\}/g);
  return matches ? matches.map((m) => m.slice(1, -1)) : [];
}

/** Get template for an action, or undefined for fallback rendering. */
export function getTemplate(actionId: string): SentenceTemplate | undefined {
  return SENTENCE_TEMPLATES[actionId];
}
