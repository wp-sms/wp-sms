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
import { fileURLToPath, pathToFileURL } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const dir = join(root, 'languages')
const handle = 'wsms-dashboard-i18n'

const unquote = (lines) => lines.map((l) => JSON.parse(l.slice(l.indexOf('"')))).join('')

export function parsePo(text) {
  const entries = []
  let header = {}
  for (const block of text.split(/\n\s*\n/)) {
    const blockLines = block.split('\n')
    const references = blockLines
      .filter((line) => line.startsWith('#:'))
      .flatMap((line) => line.slice(2).trim().split(/\s+/))
    const lines = blockLines.filter((l) => l && !l.startsWith('#'))
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
    entries.push([ctx + id, forms, references])
  }
  return { header, entries }
}

export const dashboardEntries = (entries) => entries.filter(([, , references]) =>
  references.some((reference) => reference.startsWith('public/dashboard/i18n-strings.js:'))
)

function buildLanguageFiles() {
  for (const file of readdirSync(dir).filter((f) => /^wp-sms-[a-z]{2,3}(_[A-Z]{2})?\.po$/.test(f))) {
    const po = join(dir, file)
    const locale = file.slice('wp-sms-'.length, -'.po'.length)
    execFileSync('wp', ['i18n', 'make-mo', po, dir], { stdio: 'inherit' })
    execFileSync('wp', ['i18n', 'make-php', po, dir], { stdio: 'inherit' })
    const { header, entries } = parsePo(readFileSync(po, 'utf8'))
    const dashboard = dashboardEntries(entries)
    const messages = { '': { domain: 'messages', 'plural-forms': header['Plural-Forms'] || 'nplurals=2; plural=n != 1;', lang: header.Language || locale } }
    for (const [key, forms] of dashboard) messages[key] = forms
    const json = { 'translation-revision-date': header['PO-Revision-Date'] || '', generator: 'bin/make-language-files.mjs', domain: 'messages', locale_data: { messages } }
    writeFileSync(join(dir, `wp-sms-${locale}-${handle}.json`), JSON.stringify(json))
    console.log(`${file}: ${dashboard.length} dashboard strings`)
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  buildLanguageFiles()
}
