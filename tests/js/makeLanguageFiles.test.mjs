import assert from 'node:assert/strict'
import test from 'node:test'

import { dashboardEntries, parsePo } from '../../bin/make-language-files.mjs'

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
