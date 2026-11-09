#!/usr/bin/env node
/**
 * Build the translation files shipped in languages/ from each languages/wp-sms-<locale>.po:
 *
 *   wp-sms-<locale>.mo, wp-sms-<locale>.l10n.php   PHP strings (wp i18n make-mo / make-php)
 *   wp-sms-<locale>-wsms-dashboard-i18n.json        React dashboard strings, named after the script
 *                                                   handle so wp_set_script_translations() finds it
 *
 * The shipped files only fill strings the WordPress.org language pack does not have yet;
 * the pack wins where both have a string (see WP_SMS::loadTextDomain() and
 * DashboardHandler::mergeLanguagePackScriptTranslations()).
 *
 * Run via `npm run build:languages` after editing a .po file.
 */
import { execFileSync } from 'node:child_process'
import { readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const dir = join(root, 'languages')
const handle = 'wsms-dashboard-i18n'

const unquote = (lines) => lines.map((l) => JSON.parse(l.slice(l.indexOf('"')))).join('')

function parsePo(text) {
  const entries = []
  let header = {}
  for (const block of text.split(/\n\s*\n/)) {
    const lines = block.split('\n').filter((l) => l && !l.startsWith('#'))
    const field = {}
    let key = null
    for (const line of lines) {
      const m = line.match(/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s/)
      if (m) { key = m[1]; field[key] = [line.slice(m[1].length).trim()] } else if (key && line.startsWith('"')) field[key].push(line)
    }
    if (!field.msgid) continue
    const id = unquote(field.msgid)
    if (id === '') {
      for (const l of unquote(field.msgstr || ['""']).split('\n')) {
        const i = l.indexOf(':')
        if (i > 0) header[l.slice(0, i).trim()] = l.slice(i + 1).trim()
      }
      continue
    }
    const forms = Object.keys(field).filter((k) => k.startsWith('msgstr')).sort().map((k) => unquote(field[k]))
    if (!forms.some(Boolean)) continue
    const ctx = field.msgctxt ? unquote(field.msgctxt) + '\u0004' : ''
    entries.push([ctx + id, forms])
  }
  return { header, entries }
}

for (const file of readdirSync(dir).filter((f) => /^wp-sms-[a-z]{2,3}(_[A-Z]{2})?\.po$/.test(f))) {
  const po = join(dir, file)
  const locale = file.slice('wp-sms-'.length, -'.po'.length)
  execFileSync('wp', ['i18n', 'make-mo', po, dir], { stdio: 'inherit' })
  execFileSync('wp', ['i18n', 'make-php', po, dir], { stdio: 'inherit' })
  const { header, entries } = parsePo(readFileSync(po, 'utf8'))
  const messages = { '': { domain: 'messages', 'plural-forms': header['Plural-Forms'] || 'nplurals=2; plural=n != 1;', lang: header.Language || locale } }
  for (const [key, forms] of entries) messages[key] = forms
  const json = { 'translation-revision-date': header['PO-Revision-Date'] || '', generator: 'bin/make-language-files.mjs', domain: 'messages', locale_data: { messages } }
  writeFileSync(join(dir, `wp-sms-${locale}-${handle}.json`), JSON.stringify(json))
  console.log(`${file}: ${entries.length} strings`)
}
