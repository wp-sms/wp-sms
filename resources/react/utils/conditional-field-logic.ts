import type { SchemaField } from '@/types/settings/group-schema'

export const normalizeValue = (value: unknown): unknown => {
  if (typeof value === 'boolean') return value
  if (value === 'true') return true
  if (value === 'false') return false
  if (value === '1') return true
  if (value === '0') return false
  return value
}

export const shouldShowField = (schema: SchemaField, formValues: Record<string, unknown>): boolean => {
  if (schema.hidden) return false

  const showIfEntries = Object.entries(schema.showIf ?? {})
  const hideIfEntries = Object.entries(schema.hideIf ?? {})

  const shouldShowCondition =
    showIfEntries.length === 0
      ? true // No showIf conditions means show by default
      : showIfEntries.every(([key, expectedValue]) => {
          const actualValue = formValues[key]
          return normalizeValue(actualValue) === normalizeValue(expectedValue)
        })

  const shouldHideCondition = hideIfEntries.some(([key, expectedValue]) => {
    const actualValue = formValues[key]
    return normalizeValue(actualValue) === normalizeValue(expectedValue)
  })

  return shouldShowCondition && !shouldHideCondition
}
