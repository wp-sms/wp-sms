// Jest (and any other env without a pre-loaded wp.i18n) gets identity fallbacks
// so tests can render components without a browser-side WordPress runtime.
const wpI18n = (typeof window !== 'undefined' && window.wp && window.wp.i18n) || null

const identity = (s) => s
const pluralIdentity = (singular, plural, n) => (n === 1 ? singular : plural)
const fallbackSprintf = (format, ...args) => {
  let i = 0
  return String(format).replace(/%(?:(\d+)\$)?[sd]/g, (_, pos) => {
    const idx = pos ? parseInt(pos, 10) - 1 : i++
    return args[idx] !== undefined ? args[idx] : ''
  })
}

export const __ = wpI18n ? wpI18n.__.bind(wpI18n) : identity
export const _x = wpI18n ? wpI18n._x.bind(wpI18n) : identity
export const _n = wpI18n ? wpI18n._n.bind(wpI18n) : pluralIdentity
export const _nx = wpI18n ? wpI18n._nx.bind(wpI18n) : pluralIdentity
export const sprintf = wpI18n ? wpI18n.sprintf.bind(wpI18n) : fallbackSprintf
