export interface SentenceTemplate {
  /** Each line is rendered as a sentence row. {field} becomes a token. */
  lines: string[];
  /** Fields rendered in collapsible "Settings" instead of inline. */
  advancedFields: string[];
}

export const SENTENCE_TEMPLATES: Record<string, SentenceTemplate> = {
  send_message: {
    lines: ['Send {channel} to {to}', 'Saying: {body}'],
    advancedFields: ['gateway', 'subject', 'media_url'],
  },
  http_request: {
    lines: ['Make a {method} request to {url}', 'Body: {body}'],
    advancedFields: ['headers'],
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
