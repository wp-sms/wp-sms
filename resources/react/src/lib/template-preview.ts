import type { JsonSchema, JsonSchemaProperty } from '@/lib/api';

/** Format sample data as a readable string for display. */
export function formatSampleData(data: Record<string, unknown>, indent = 0): string {
  const lines: string[] = [];
  const pad = '  '.repeat(indent);
  for (const [key, value] of Object.entries(data)) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      lines.push(`${pad}${key}:`);
      lines.push(formatSampleData(value as Record<string, unknown>, indent + 1));
    } else {
      lines.push(`${pad}${key}: ${JSON.stringify(value)}`);
    }
  }
  return lines.join('\n');
}

/** Extract example data recursively from a JSON schema. */
export function extractExampleData(schema: JsonSchema): Record<string, unknown> {
  if (!schema.properties) return {};
  return extractFromProperties(schema.properties);
}

function extractFromProperties(
  properties: Record<string, JsonSchemaProperty>,
): Record<string, unknown> {
  const data: Record<string, unknown> = {};
  for (const [key, prop] of Object.entries(properties)) {
    if (prop.type === 'object' && prop.properties) {
      data[key] = extractFromProperties(prop.properties);
    } else if (prop.example != null) {
      data[key] = prop.example;
    }
  }
  return data;
}

/** Resolve a dot-path against nested data. */
function resolveValue(path: string, data: Record<string, unknown>): unknown {
  const parts = path.split('.');
  let current: unknown = data;
  for (const part of parts) {
    if (current == null || typeof current !== 'object') return undefined;
    current = (current as Record<string, unknown>)[part];
  }
  return current;
}

/**
 * Resolve `{{path}}` template variables in a string against schema example data.
 * Returns null if the template contains no variables.
 */
export function resolveTemplatePreview(
  template: string,
  schema: JsonSchema,
  sampleData?: Record<string, unknown>,
): string | null {
  if (!template.includes('{{')) return null;

  const data = sampleData ?? extractExampleData(schema);
  if (Object.keys(data).length === 0) return null;

  let hasResolution = false;

  const resolved = template.replace(/\{\{([^}]+)\}\}/g, (match, path: string) => {
    const value = resolveValue(path.trim(), data);
    if (value !== undefined) {
      hasResolution = true;
      return String(value);
    }
    return match;
  });

  return hasResolution ? resolved : null;
}

/** Find template variables that don't resolve against the given schema. */
export function findUnresolvedVariables(
  template: string,
  schema: JsonSchema,
): string[] {
  if (!template.includes('{{')) return [];
  const data = extractExampleData(schema);
  return [...template.matchAll(/\{\{([^}]+)\}\}/g)]
    .map((m) => m[1].trim())
    .filter((path) => resolveValue(path, data) === undefined);
}
