#!/usr/bin/env node
/**
 * copy-static.mjs
 * Copies static assets from resources/ → public/
 */

import { cpSync, mkdirSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..')
const res = resolve(root, 'resources')
const dest = resolve(root, 'public')

const mappings = [
  { from: 'fonts',      to: 'fonts' },
  { from: 'images',     to: 'images' },
  { from: 'vendor-css', to: 'css' },
  { from: 'vendor-js',  to: 'js' },
]

for (const { from, to } of mappings) {
  const srcPath = resolve(res, from)
  const destPath = resolve(dest, to)

  try {
    mkdirSync(dirname(destPath), { recursive: true })
    cpSync(srcPath, destPath, { recursive: true, force: true })
    console.log(`  ${from} → public/${to}`)
  } catch (err) {
    if (err.code !== 'ENOENT') throw err
    console.warn(`  ⚠ skipped ${from} (not found)`)
  }
}

console.log('Static assets copied.')
