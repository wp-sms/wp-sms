#!/usr/bin/env node
/**
 * Emit resources/json/phone-number-metadata.json from the libphonenumber metadata
 * that ships inside the bundled intl-tel-input utils (resources/vendor-js/intel/utils.js).
 *
 * The PHP side (WP_SMS\Components\PhoneNumberMetadata) uses it to tell whether a
 * number such as +7065810032 is a real number for the country its digits point at,
 * without adding a PHP phone library to the plugin.
 *
 * Output shape, keyed by country calling code:
 *
 *   { "1": [ { "region": "US", "general": "[2-9]\\d{9}|3\\d{6}", "types": ["..."] }, ... ] }
 *
 * `general` is the region's overall national number pattern and `types` are the
 * patterns for each number type (fixed line, mobile, toll free, ...). A national
 * number is valid for a region when it fully matches `general` and at least one type.
 *
 * Run with `node bin/build-phone-metadata.mjs` after updating intl-tel-input.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const source = readFileSync(resolve(root, 'resources/vendor-js/intel/utils.js'), 'utf8')

// The region metadata is a single object literal that starts with the Ascension Island entry.
const marker = source.search(/[A-Za-z_$][\w$]*=\{AC:\[/)
if (marker === -1) {
  throw new Error('Could not find the region metadata in utils.js')
}
const start = source.indexOf('{', marker)

// Walk to the matching closing brace, skipping string literals (patterns contain braces).
let depth = 0
let end = -1
for (let i = start; i < source.length; i++) {
  const ch = source[i]
  if (ch === '"' || ch === "'") {
    const quote = ch
    i++
    while (i < source.length && source[i] !== quote) {
      if (source[i] === '\\') i++
      i++
    }
    continue
  }
  if (ch === '{') depth++
  if (ch === '}') {
    depth--
    if (depth === 0) {
      end = i
      break
    }
  }
}
if (end === -1) {
  throw new Error('Could not find the end of the region metadata in utils.js')
}

// eslint-disable-next-line no-new-func
const regions = new Function(`return ${source.slice(start, end + 1)}`)()

// PhoneMetadata field positions (libphonenumber phonemetadata.proto).
const GENERAL_DESC = 1
const TYPE_DESCS = [2, 3, 4, 5, 6, 7, 8, 21, 25, 28] // fixed line, mobile, toll free, premium, shared cost, personal, voip, pager, uan, voicemail
const COUNTRY_CODE = 10
const MAIN_COUNTRY_FOR_CODE = 22
const PATTERN = 2

const out = {}
for (const [region, meta] of Object.entries(regions)) {
  if (!Array.isArray(meta) || typeof meta[COUNTRY_CODE] !== 'number') continue
  const general = meta[GENERAL_DESC] && meta[GENERAL_DESC][PATTERN]
  if (typeof general !== 'string') continue

  const types = TYPE_DESCS
    .map((index) => meta[index] && meta[index][PATTERN])
    .filter((pattern) => typeof pattern === 'string' && pattern !== '')

  const code = String(meta[COUNTRY_CODE])
  out[code] = out[code] || []
  const entry = { region, general, types }
  if (meta[MAIN_COUNTRY_FOR_CODE]) {
    out[code].unshift(entry)
  } else {
    out[code].push(entry)
  }
}

const sorted = Object.fromEntries(Object.keys(out).sort((a, b) => Number(a) - Number(b)).map((k) => [k, out[k]]))
writeFileSync(resolve(root, 'resources/json/phone-number-metadata.json'), JSON.stringify(sorted) + '\n')
console.log(`Wrote ${Object.keys(sorted).length} calling codes`)
