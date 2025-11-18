#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

const potFile = resolve(__dirname, '../languages/wp-sms.pot')

try {
  let content = readFileSync(potFile, 'utf-8')

  // Replace public/i18n-temp/ paths with public/react/
  // The i18n-temp build is only for extracting strings
  // The actual production files are in public/react/
  content = content.replace(/public\/i18n-temp\//g, 'public/react/')

  writeFileSync(potFile, content, 'utf-8')
  console.log('✓ Fixed POT file paths: public/i18n-temp/ → public/react/')
} catch (error) {
  console.error('Error fixing POT paths:', error.message)
  process.exit(1)
}
