import { useMemo } from 'react'
import { getWpSettings } from '@/lib/utils'

/**
 * Hook to get add-on settings for a specific page
 *
 * Parses the add-on settings schema from wpSmsSettings and returns
 * sections and fields organized for the specified page.
 *
 * @param {string} pageId - The page ID to get settings for (e.g., 'integrations', 'notifications')
 * @returns {object} Object containing sections, fieldsBySection, standaloneFields, and hasAddonContent
 *
 * @example
 * const { sections, fieldsBySection, hasAddonContent } = useAddonSettings('integrations')
 */
export function useAddonSettings(pageId) {
  const { addonSettings = {} } = getWpSettings()

  return useMemo(() => {
    const sections = []
    const fieldsBySection = {}
    const standaloneFields = []

    // Process each add-on's settings
    Object.entries(addonSettings).forEach(([addonSlug, schema]) => {
      // Collect sections for this page
      if (schema.sections && Array.isArray(schema.sections)) {
        schema.sections.forEach(section => {
          if (section.page === pageId) {
            sections.push({
              ...section,
              addonSlug,
              addonName: schema.name,
            })
            // Initialize field array for this section
            if (!fieldsBySection[section.id]) {
              fieldsBySection[section.id] = []
            }
          }
        })
      }

      // Collect fields for this page
      if (schema.fields && Array.isArray(schema.fields)) {
        schema.fields.forEach(field => {
          if (field.target?.page === pageId) {
            const sectionId = field.target.section
            const enrichedField = {
              ...field,
              addonSlug,
              addonName: schema.name,
            }

            if (sectionId) {
              // Initialize section array if not exists
              if (!fieldsBySection[sectionId]) {
                fieldsBySection[sectionId] = []
              }
              fieldsBySection[sectionId].push(enrichedField)
            } else {
              // No section specified, add to standalone fields
              standaloneFields.push(enrichedField)
            }
          }
        })
      }
    })

    // Sort sections by priority
    sections.sort((a, b) => (a.priority || 100) - (b.priority || 100))

    // Sort fields within each section by priority
    Object.keys(fieldsBySection).forEach(sectionId => {
      fieldsBySection[sectionId].sort((a, b) =>
        (a.target?.priority || 100) - (b.target?.priority || 100)
      )
    })

    // Sort standalone fields by priority
    standaloneFields.sort((a, b) =>
      (a.target?.priority || 100) - (b.target?.priority || 100)
    )

    return {
      sections,
      fieldsBySection,
      standaloneFields,
      hasAddonContent: sections.length > 0 ||
        standaloneFields.length > 0 ||
        Object.values(fieldsBySection).some(fields => fields.length > 0),
    }
  }, [pageId, addonSettings])
}

/**
 * Hook to get add-on fields for a specific section on a page
 *
 * Useful when you want to inject add-on fields into an existing section
 * that's defined by the core plugin.
 *
 * @param {string} pageId - The page ID
 * @param {string} sectionId - The section ID to get fields for
 * @returns {array} Array of add-on fields for the section
 *
 * @example
 * const wooFields = useAddonFieldsForSection('integrations', 'woocommerce')
 */
export function useAddonFieldsForSection(pageId, sectionId) {
  const { fieldsBySection } = useAddonSettings(pageId)
  return fieldsBySection[sectionId] || []
}

/**
 * Hook to check if any add-on content exists for a page
 *
 * @param {string} pageId - The page ID
 * @returns {boolean} Whether there's any add-on content for the page
 */
export function useHasAddonContent(pageId) {
  const { hasAddonContent } = useAddonSettings(pageId)
  return hasAddonContent
}

/**
 * Get all add-on settings schemas
 *
 * Useful for debugging or when you need access to the raw schemas.
 *
 * @returns {object} All add-on settings schemas keyed by addon slug
 */
export function getAddonSettingsSchemas() {
  const { addonSettings = {} } = getWpSettings()
  return addonSettings
}

export default useAddonSettings
