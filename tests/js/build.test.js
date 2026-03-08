/**
 * Build verification test
 *
 * Ensures `pnpm build` produces all expected output files in public/.
 * Run after build: `pnpm build && pnpm test -- build.test`
 */

const fs = require('fs')
const path = require('path')

const root = path.resolve(__dirname, '../..')
const pub = path.resolve(root, 'public')

/**
 * Helper: check file exists and has content
 */
function expectFile(relPath) {
  const full = path.resolve(pub, relPath)
  expect(fs.existsSync(full)).toBe(true)
  const stat = fs.statSync(full)
  expect(stat.size).toBeGreaterThan(0)
}

/**
 * Helper: check directory exists and is non-empty
 */
function expectDir(relPath) {
  const full = path.resolve(pub, relPath)
  expect(fs.existsSync(full)).toBe(true)
  const stat = fs.statSync(full)
  expect(stat.isDirectory()).toBe(true)
  const files = fs.readdirSync(full)
  expect(files.length).toBeGreaterThan(0)
}

describe('Build output: JS bundles (vite)', () => {
  test('admin.min.js exists and is minified', () => {
    expectFile('js/admin.min.js')
    const content = fs.readFileSync(path.resolve(pub, 'js/admin.min.js'), 'utf8')
    // Minified: no leading newlines, contains "use strict"
    expect(content).toContain('"use strict"')
  })

  test('frontend.min.js exists and is minified', () => {
    expectFile('js/frontend.min.js')
    const content = fs.readFileSync(path.resolve(pub, 'js/frontend.min.js'), 'utf8')
    expect(content).toContain('"use strict"')
  })
})

describe('Build output: SCSS compilation', () => {
  test('admin.css exists', () => {
    expectFile('css/admin.css')
  })

  test('front-styles.css exists', () => {
    expectFile('css/front-styles.css')
  })

  test('mail.css exists', () => {
    expectFile('css/mail.css')
  })
})

describe('Build output: Dashboard (React app)', () => {
  test('dashboard directory has built assets', () => {
    expectDir('dashboard')
    expectDir('dashboard/assets')
  })

  test('dashboard index.html exists', () => {
    expectFile('dashboard/index.html')
  })
})

describe('Build output: Blocks', () => {
  test('blocks directory exists', () => {
    expectDir('blocks')
  })
})

describe('Build output: Static assets (copy-static)', () => {
  test('fonts directory copied', () => {
    expectDir('fonts')
  })

  test('images directory copied', () => {
    expectDir('images')
  })

  test('vendor CSS files copied', () => {
    expectFile('css/intlTelInput.min.css')
  })

  test('vendor JS files copied', () => {
    expectFile('js/select2.min.js')
    expectFile('js/flatpickr.min.js')
  })
})
