/**
 * i18n drift-prevention test: ensures every translatable string in compiled JS
 * bundles has a non-empty, non-fuzzy PO translation with matching placeholders.
 *
 * Catches:
 * - Missing translations for new strings added in source
 * - Placeholder mismatches from stale msgmerge fuzzy matches
 * - Source reference drift (PO entries pointing at .tsx instead of compiled paths)
 *
 * Run `npm run build && npm run build:pot && msgmerge ...` to regenerate,
 * then `npm test` to verify.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

const ROOT = resolve(__dirname, '../../../../..');

/** JS bundles to scan for translatable strings. */
const BUNDLES = [
  'public/app/main.js',
  'public/auth/app.js',
  'public/auth/messaging-button.js',
  'public/auth/subscription-form.js',
  'public/auth/verify-widget.js',
  'public/auth/user-button.js',
] as const;

const PO_PATH = resolve(ROOT, 'public/languages/wp-sms-fa_IR.po');

/**
 * Strings where msgid === msgstr is legitimate (brand names, URLs, acronyms).
 * Matched as "starts with" or exact match or regex.
 */
const IDENTICAL_ALLOW_PATTERNS = [
  /^https?:\/\//, // URLs
  /^[A-Za-z0-9_.@:\/\-#?&=%+,;()\[\]{}<>*!~ ]+$/, // all-ASCII (acronyms, brand names, code)
];

// ---------------------------------------------------------------------------
// JS string extraction
// ---------------------------------------------------------------------------

/** Extract all `__("string", "wp-sms")` calls from minified JS. */
function extractTranslateStrings(code: string): string[] {
  const re = /\(0,\w+\.__\)\("((?:[^"\\]|\\.)*)","wp-sms"\)/g;
  const strings: string[] = [];
  let m: RegExpExecArray | null;
  while ((m = re.exec(code))) {
    strings.push(unescapeJs(m[1]));
  }
  return strings;
}

/** Extract all `_n("singular", "plural", n, "wp-sms")` calls from minified JS. */
function extractPluralStrings(code: string): Array<{ singular: string; plural: string }> {
  const re = /\(0,\w+\._n\)\("((?:[^"\\]|\\.)*)","((?:[^"\\]|\\.)*)",\w+,"wp-sms"\)/g;
  const results: Array<{ singular: string; plural: string }> = [];
  let m: RegExpExecArray | null;
  while ((m = re.exec(code))) {
    results.push({ singular: unescapeJs(m[1]), plural: unescapeJs(m[2]) });
  }
  return results;
}

function unescapeJs(s: string): string {
  return s
    .replace(/\\'/g, "'")
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, '\\')
    .replace(/\\n/g, '\n')
    .replace(/\\t/g, '\t');
}

// ---------------------------------------------------------------------------
// PO file parsing
// ---------------------------------------------------------------------------

interface PoEntry {
  msgid: string;
  msgid_plural: string | null;
  msgstr: string[];
  fuzzy: boolean;
  references: string[];
}

function parsePo(content: string): PoEntry[] {
  const lines = content.split('\n');
  const entries: PoEntry[] = [];
  let current: PoEntry = makeEmptyEntry();
  let lastField: 'msgid' | 'msgid_plural' | 'msgstr' | null = null;
  let msgstrIndex = 0;

  for (const line of lines) {
    if (line.startsWith('#, ') && line.includes('fuzzy')) {
      current.fuzzy = true;
      continue;
    }

    if (line.startsWith('#: ')) {
      current.references.push(...line.slice(3).trim().split(/\s+/));
      continue;
    }

    if (line.startsWith('#')) continue;

    if (line.startsWith('msgid_plural ')) {
      current.msgid_plural = extractQuoted(line.slice('msgid_plural '.length));
      lastField = 'msgid_plural';
      continue;
    }

    if (line.startsWith('msgid ')) {
      if (current.msgid !== '' || current.msgstr.some((s) => s !== '')) {
        entries.push(current);
        current = makeEmptyEntry();
      }
      current.msgid = extractQuoted(line.slice('msgid '.length));
      lastField = 'msgid';
      continue;
    }

    const msgstrMatch = line.match(/^msgstr(?:\[(\d+)\])?\s/);
    if (msgstrMatch) {
      msgstrIndex = msgstrMatch[1] !== undefined ? parseInt(msgstrMatch[1], 10) : 0;
      while (current.msgstr.length <= msgstrIndex) current.msgstr.push('');
      current.msgstr[msgstrIndex] = extractQuoted(line.slice(line.indexOf('"')));
      lastField = 'msgstr';
      continue;
    }

    // Continuation line (starts with ")
    if (line.startsWith('"') && lastField) {
      const text = extractQuoted(line);
      if (lastField === 'msgid') {
        current.msgid += text;
      } else if (lastField === 'msgid_plural') {
        current.msgid_plural = (current.msgid_plural ?? '') + text;
      } else if (lastField === 'msgstr') {
        current.msgstr[msgstrIndex] = (current.msgstr[msgstrIndex] ?? '') + text;
      }
      continue;
    }

    // Blank line — flush
    if (line.trim() === '' && (current.msgid !== '' || current.msgstr.some((s) => s !== ''))) {
      entries.push(current);
      current = makeEmptyEntry();
      lastField = null;
    }
  }

  // Flush last entry
  if (current.msgid !== '' || current.msgstr.some((s) => s !== '')) {
    entries.push(current);
  }

  return entries;
}

function makeEmptyEntry(): PoEntry {
  return { msgid: '', msgid_plural: null, msgstr: [''], fuzzy: false, references: [] };
}

function extractQuoted(s: string): string {
  const match = s.match(/^"(.*)"$/);
  if (!match) return '';
  return match[1]
    .replace(/\\n/g, '\n')
    .replace(/\\t/g, '\t')
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, '\\');
}

// ---------------------------------------------------------------------------
// Placeholder extraction
// ---------------------------------------------------------------------------

/** Extract printf-style placeholders from a string. Returns sorted array. */
function extractPlaceholders(s: string): string[] {
  const re = /%(?:\d+\$)?[sdf]/g;
  const matches = s.match(re);
  return matches ? matches.sort() : [];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('i18n coverage', () => {
  // Collect all translatable strings from all bundles
  // singularStrings = strings from __() calls + singular forms from _n() calls
  // pluralStrings tracks _n() pairs separately for msgid_plural validation
  const singularStrings = new Set<string>();
  const pluralStrings: Array<{ singular: string; plural: string }> = [];
  const bundleErrors: string[] = [];

  for (const bundle of BUNDLES) {
    const path = resolve(ROOT, bundle);
    if (!existsSync(path)) {
      bundleErrors.push(`Bundle missing: ${bundle}`);
      continue;
    }
    const code = readFileSync(path, 'utf-8');
    for (const s of extractTranslateStrings(code)) singularStrings.add(s);
    for (const p of extractPluralStrings(code)) {
      singularStrings.add(p.singular);
      // plural form is NOT added to singularStrings — it's checked via msgid_plural
      pluralStrings.push(p);
    }
  }

  // Parse PO
  let poEntries: PoEntry[] = [];
  let poByMsgid: Map<string, PoEntry> = new Map();

  if (existsSync(PO_PATH)) {
    const poContent = readFileSync(PO_PATH, 'utf-8');
    poEntries = parsePo(poContent);
    for (const entry of poEntries) {
      if (entry.msgid) poByMsgid.set(entry.msgid, entry);
    }
  }

  it('all JS bundles exist', () => {
    expect(bundleErrors).toEqual([]);
  });

  it('PO file exists and has entries', () => {
    expect(existsSync(PO_PATH)).toBe(true);
    expect(poEntries.length).toBeGreaterThan(0);
  });

  it('every JS string has a non-empty, non-fuzzy PO translation', () => {
    const missing: string[] = [];
    const fuzzy: string[] = [];
    const empty: string[] = [];

    for (const str of singularStrings) {
      const entry = poByMsgid.get(str);
      if (!entry) {
        missing.push(str);
        continue;
      }
      if (entry.fuzzy) {
        fuzzy.push(str);
        continue;
      }
      if (!entry.msgstr[0]) {
        empty.push(str);
      }
    }

    if (missing.length > 0) {
      console.log(`\nMissing PO entries (${missing.length}):`);
      missing.slice(0, 20).forEach((s) => console.log(`  - "${s}"`));
    }
    if (fuzzy.length > 0) {
      console.log(`\nFuzzy PO entries (${fuzzy.length}):`);
      fuzzy.slice(0, 20).forEach((s) => console.log(`  - "${s}"`));
    }
    if (empty.length > 0) {
      console.log(`\nEmpty translations (${empty.length}):`);
      empty.slice(0, 20).forEach((s) => console.log(`  - "${s}"`));
    }

    expect(missing).toEqual([]);
    expect(fuzzy).toEqual([]);
    expect(empty).toEqual([]);
  });

  it('placeholder counts match between msgid and msgstr', () => {
    const mismatches: Array<{ msgid: string; expected: string[]; actual: string[] }> = [];

    for (const str of singularStrings) {
      const entry = poByMsgid.get(str);
      if (!entry || entry.fuzzy || !entry.msgstr[0]) continue;

      const expected = extractPlaceholders(entry.msgid);
      const actual = extractPlaceholders(entry.msgstr[0]);

      if (expected.length > 0 && JSON.stringify(expected) !== JSON.stringify(actual)) {
        mismatches.push({ msgid: entry.msgid, expected, actual });
      }
    }

    if (mismatches.length > 0) {
      console.log(`\nPlaceholder mismatches (${mismatches.length}):`);
      mismatches.forEach(({ msgid, expected, actual }) => {
        console.log(`  "${msgid}": expected [${expected}] got [${actual}]`);
      });
    }

    expect(mismatches).toEqual([]);
  });

  it('plural forms have matching msgid_plural and translations for all indices', () => {
    const missingPlural: string[] = [];
    const wrongPlural: Array<{ singular: string; expected: string; actual: string | null }> = [];
    const incomplete: string[] = [];

    for (const { singular, plural } of pluralStrings) {
      const entry = poByMsgid.get(singular);
      if (!entry || entry.fuzzy) continue;

      if (!entry.msgid_plural) {
        missingPlural.push(singular);
        continue;
      }

      if (entry.msgid_plural !== plural) {
        wrongPlural.push({ singular, expected: plural, actual: entry.msgid_plural });
        continue;
      }

      // Farsi has nplurals=2, so we need msgstr[0] and msgstr[1]
      if (entry.msgstr.length < 2 || !entry.msgstr[0] || !entry.msgstr[1]) {
        incomplete.push(singular);
      }
    }

    if (missingPlural.length > 0) {
      console.log(`\nMissing msgid_plural in PO (${missingPlural.length}):`);
      missingPlural.forEach((s) => console.log(`  - "${s}"`));
    }
    if (wrongPlural.length > 0) {
      console.log(`\nWrong msgid_plural (${wrongPlural.length}):`);
      wrongPlural.forEach(({ singular, expected, actual }) =>
        console.log(`  "${singular}": expected "${expected}", got "${actual}"`)
      );
    }
    if (incomplete.length > 0) {
      console.log(`\nIncomplete plural translations (${incomplete.length}):`);
      incomplete.forEach((s) => console.log(`  - "${s}"`));
    }

    expect(missingPlural).toEqual([]);
    expect(wrongPlural).toEqual([]);
    expect(incomplete).toEqual([]);
  });

  it('JS-sourced PO entries use compiled paths, not .tsx source paths', () => {
    const compiledPathPrefixes = ['public/app/', 'public/auth/', 'public/js/'];
    const bad: Array<{ msgid: string; refs: string[] }> = [];

    for (const str of singularStrings) {
      const entry = poByMsgid.get(str);
      if (!entry || entry.references.length === 0) continue;

      const tsxRefs = entry.references.filter(
        (ref) => ref.includes('.tsx') || ref.includes('.ts')
      );
      const hasCompiledRef = entry.references.some((ref) =>
        compiledPathPrefixes.some((p) => ref.startsWith(p))
      );

      // Only flag if entry has .tsx refs but NO compiled refs
      // (some entries legitimately appear in both PHP and JS)
      if (tsxRefs.length > 0 && !hasCompiledRef) {
        bad.push({ msgid: str, refs: tsxRefs });
      }
    }

    if (bad.length > 0) {
      console.log(`\nPO entries with .tsx refs but no compiled path (${bad.length}):`);
      bad.slice(0, 10).forEach(({ msgid, refs }) => {
        console.log(`  "${msgid}" → ${refs.join(', ')}`);
      });
    }

    expect(bad).toEqual([]);
  });

  it('identical msgid/msgstr entries are only for ASCII-only or URL strings', () => {
    const suspicious: string[] = [];

    for (const str of singularStrings) {
      const entry = poByMsgid.get(str);
      if (!entry || entry.fuzzy || !entry.msgstr[0]) continue;
      if (entry.msgid !== entry.msgstr[0]) continue;

      // Check if this identity is expected
      const isAllowed = IDENTICAL_ALLOW_PATTERNS.some((p) => p.test(entry.msgid));
      if (!isAllowed) {
        suspicious.push(entry.msgid);
      }
    }

    if (suspicious.length > 0) {
      console.log(`\nSuspicious identical translations (${suspicious.length}):`);
      suspicious.slice(0, 20).forEach((s) => console.log(`  - "${s}"`));
    }

    expect(suspicious).toEqual([]);
  });
});
