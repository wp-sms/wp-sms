#!/usr/bin/env node
/**
 * Emit public/dashboard/i18n-strings.js — a generated file whose only job is to
 * expose every React admin label to WordPress.org's string scanner.
 *
 * Why this exists: the plugin ZIP ships the compiled dashboard bundle, not the
 * JSX sources, and WordPress.org builds its translation set from what it finds
 * in the shipped files. Minification folds patterns like
 *
 *   cond ? __( 'A', 'wp-sms' ) : __( 'B', 'wp-sms' )
 *
 * into `__( cond ? "A" : "B", "wp-sms" )`, where the first argument is no longer
 * a literal — so the scanner drops both labels and translators never see them.
 *
 * This file restates every label as a plain literal call. The generated wrapper
 * is intentionally not invoked; the file is enqueued as a stable classic-script
 * anchor so WordPress can load its matching JSON language pack before the React
 * module. It must stay JavaScript: browser-side translation files only cover
 * labels WordPress.org found in JS.
 *
 * Run via `npm run build:i18n-strings`, after the dashboard build and before
 * `build:pot`.
 */

import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const pluginRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const sourceDir = 'resources/react'
const outputPath = 'public/dashboard/i18n-strings.js'
const textDomain = 'wp-sms'

/**
 * Decode a PO-escaped string literal body into its real value.
 */
function decodePo(value) {
  return value.replace(/\\(.)/g, (match, char) => {
    switch (char) {
      case 'n': return '\n'
      case 't': return '\t'
      case 'r': return '\r'
      case '\\': return '\\'
      case '"': return '"'
      default: return char
    }
  })
}

/**
 * Parse the subset of PO we need — msgctxt, msgid and msgid_plural — from each
 * blank-line-separated block, honouring the multi-line continuation form.
 */
function parsePot(contents) {
  const wanted = ['msgctxt', 'msgid', 'msgid_plural']

  return contents
    .split(/\n\s*\n/)
    .map((block) => {
      const entry = { msgctxt: '', msgid: '', msgid_plural: '', references: '' }
      let field = null

      for (const rawLine of block.split('\n')) {
        const line = rawLine.trim()
        if (line.startsWith('#:')) {
          entry.references += ` ${line.slice(2).trim()}`
          continue
        }
        if (line === '' || line.startsWith('#')) continue

        const keyed = line.match(/^([a-z_]+)(?:\[\d+\])?\s+"([\s\S]*)"$/)
        if (keyed) {
          field = wanted.includes(keyed[1]) ? keyed[1] : null
          if (field) entry[field] += decodePo(keyed[2])
          continue
        }

        const continuation = line.match(/^"([\s\S]*)"$/)
        if (continuation && field) entry[field] += decodePo(continuation[1])
      }

      return entry
    })
    // make-pot always folds in the plugin header strings; keep only labels that
    // genuinely come from the React sources.
    .filter((entry) => entry.msgid !== '' && entry.references.includes(`${sourceDir}/`))
}

const tempDir = mkdtempSync(join(tmpdir(), 'wpsms-i18n-'))
const tempPot = join(tempDir, 'react.pot')

try {
  execFileSync(
    'wp',
    ['i18n', 'make-pot', '.', tempPot, `--include=${sourceDir}`, '--skip-audit'],
    { cwd: pluginRoot, stdio: ['ignore', 'ignore', 'inherit'] }
  )

  const entries = parsePot(readFileSync(tempPot, 'utf8'))
  const domain = JSON.stringify(textDomain)

  const calls = entries.map((entry) => {
    const id = JSON.stringify(entry.msgid)

    if (entry.msgid_plural) {
      const plural = JSON.stringify(entry.msgid_plural)
      return entry.msgctxt
        ? `_nx( ${id}, ${plural}, 1, ${JSON.stringify(entry.msgctxt)}, ${domain} );`
        : `_n( ${id}, ${plural}, 1, ${domain} );`
    }

    return entry.msgctxt
      ? `_x( ${id}, ${JSON.stringify(entry.msgctxt)}, ${domain} );`
      : `__( ${id}, ${domain} );`
  })

  const contents = `/**
 * GENERATED FILE — DO NOT EDIT.
 *
 * Produced by bin/emit-i18n-strings.mjs from ${sourceDir}. Restates every React
 * admin label as a literal call so WordPress.org's scanner can collect the ones
 * minification hides inside the compiled bundle.
 *
 * Enqueued as a translation anchor; the wrapper is intentionally not invoked.
 */
( function ( __, _x, _n, _nx ) {
${calls.map((call) => `\t${call}`).join('\n')}
} );
`

  writeFileSync(join(pluginRoot, outputPath), contents)
  console.log(`Emitted ${entries.length} strings to ${outputPath}`)
} finally {
  rmSync(tempDir, { recursive: true, force: true })
}
