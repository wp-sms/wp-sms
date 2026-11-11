import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

import { dashboardEntries, parsePo } from '../../bin/make-language-files.mjs'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

test('dashboard catalog excludes PHP-only translations', () => {
  const po = `msgid ""
msgstr ""
"Language: fa_IR\\n"
"Plural-Forms: nplurals=2; plural=n > 1;\\n"

#: includes/admin.php:10
msgid "PHP only"
msgstr "فقط پی‌اچ‌پی"

#: public/dashboard/i18n-strings.js:5
#: resources/react/src/App.jsx:12
msgid "Dashboard label"
msgstr "برچسب پیشخوان"
`

  const { entries } = parsePo(po)
  const dashboard = dashboardEntries(entries)

  assert.deepEqual(dashboard.map(([key]) => key), ['Dashboard label'])
})

test('PO parser accepts Windows and legacy Mac line endings', () => {
  const po = [
    'msgid ""',
    'msgstr ""',
    '"Language: fa_IR\\n"',
    '',
    '#: public/dashboard/i18n-strings.js:5',
    'msgid "Dashboard label"',
    'msgstr "برچسب پیشخوان"',
    '',
  ].join('\r\n')

  const { entries } = parsePo(po.replace('msgid "Dashboard label"\r\n', 'msgid "Dashboard label"\r'))

  assert.deepEqual(entries.map(([key, forms]) => [key, forms]), [
    ['Dashboard label', ['برچسب پیشخوان']],
  ])
})

test('shipped Persian files cover the corrected source strings', () => {
  const { entries } = parsePo(readFileSync(join(root, 'languages/wp-sms-fa_IR.po'), 'utf8'))
  const translated = new Map(entries.map(([key, forms]) => [key, forms]))
  const corrected = [
    '%s users have a mobile number.',
    'User with %s mobile number is removed completely!',
    'No gateways found matching "%s".',
    "Your license includes the %s, but it's not installed yet. Go to the Add-Ons page to install and activate it, so you can start using all its features.",
    'This add-on does not have an active license, which means it cannot receive updates, including important security updates. For uninterrupted access to updates and to keep your site secure, we strongly recommend activating a license.',
  ]

  for (const msgid of corrected) {
    assert.ok(translated.get(msgid)?.some(Boolean), `Missing Persian translation for: ${msgid}`)
  }

  const headerHelp = [...translated.keys()].find((msgid) => msgid.startsWith('One <code>Header-Name: value</code> per line.'))
  assert.ok(headerHelp?.endsWith('</code>.'), 'The corrected custom-header help string must be translated')

  const dashboard = JSON.parse(readFileSync(join(root, 'languages/wp-sms-fa_IR-wsms-dashboard-i18n.json'), 'utf8'))
  const messages = dashboard.locale_data.messages
  for (const msgid of corrected.slice(2)) {
    assert.ok(messages[msgid]?.some(Boolean), `Missing dashboard translation for: ${msgid}`)
  }
})
