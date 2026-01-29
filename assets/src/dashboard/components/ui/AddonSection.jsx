import React, { useState } from 'react'
import PropTypes from 'prop-types'
import * as Icons from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from './card'
import { DynamicField } from './DynamicField'

/**
 * Get an icon component by name from lucide-react
 *
 * @param {string} iconName - Name of the Lucide icon (PascalCase)
 * @returns {React.Component|null} The icon component or null if not found
 */
function getIconComponent(iconName) {
  if (!iconName) return null

  // Try exact match first
  if (Icons[iconName]) {
    return Icons[iconName]
  }

  // Try common variations
  const variations = [
    iconName,
    iconName.charAt(0).toUpperCase() + iconName.slice(1), // Capitalize first letter
    iconName.replace(/-([a-z])/g, (_, char) => char.toUpperCase()), // kebab-case to PascalCase
  ]

  for (const variant of variations) {
    if (Icons[variant]) {
      return Icons[variant]
    }
  }

  return null
}

/**
 * AddonSection - Renders a section card with add-on fields
 *
 * This component renders a section defined by an add-on via the
 * wpsms_addon_settings_schema filter. It displays a card with
 * a title, description, icon, and dynamically rendered fields.
 *
 * @param {Object} props
 * @param {Object} props.section - Section definition from add-on schema
 * @param {Array} props.fields - Array of field definitions for this section
 */
export function AddonSection({ section, fields }) {
  const [isOpen, setIsOpen] = useState(false)

  // Get icon component dynamically
  const IconComponent = getIconComponent(section.icon) || Icons.Puzzle
  const ChevronIcon = isOpen ? Icons.ChevronUp : Icons.ChevronDown

  // Sort fields by priority
  const sortedFields = [...fields].sort((a, b) =>
    (a.target?.priority || 100) - (b.target?.priority || 100)
  )

  // Don't render if no fields
  if (sortedFields.length === 0) {
    return null
  }

  return (
    <Card>
      <CardHeader
        className="wsms-cursor-pointer wsms-select-none"
        onClick={() => setIsOpen(!isOpen)}
      >
        <div className="wsms-flex wsms-items-center wsms-justify-between">
          <div>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <IconComponent className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {section.title}
            </CardTitle>
            {section.description && (
              <CardDescription className="wsms-mt-1">{section.description}</CardDescription>
            )}
          </div>
          <ChevronIcon className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
        </div>
      </CardHeader>
      {isOpen && (
        <CardContent className="wsms-space-y-4 wsms-border-t wsms-pt-4">
          {sortedFields.map((field) => (
            <DynamicField key={field.id} field={field} />
          ))}
        </CardContent>
      )}
    </Card>
  )
}

AddonSection.propTypes = {
  section: PropTypes.shape({
    id: PropTypes.string.isRequired,
    title: PropTypes.string.isRequired,
    description: PropTypes.string,
    icon: PropTypes.string,
    page: PropTypes.string.isRequired,
    priority: PropTypes.number,
    addonSlug: PropTypes.string,
    addonName: PropTypes.string,
  }).isRequired,
  fields: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string.isRequired,
      type: PropTypes.string.isRequired,
      target: PropTypes.shape({
        priority: PropTypes.number,
      }),
    })
  ).isRequired,
}

/**
 * AddonFieldsInjection - Renders add-on fields to be injected into existing sections
 *
 * Use this component when you want to inject add-on fields into a section
 * that's defined by the core plugin.
 *
 * @param {Object} props
 * @param {Array} props.fields - Array of field definitions to render
 */
export function AddonFieldsInjection({ fields }) {
  if (!fields || fields.length === 0) {
    return null
  }

  // Sort fields by priority
  const sortedFields = [...fields].sort((a, b) =>
    (a.target?.priority || 100) - (b.target?.priority || 100)
  )

  return (
    <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t wsms-border-border">
      {sortedFields.map((field) => (
        <DynamicField key={field.id} field={field} />
      ))}
    </div>
  )
}

AddonFieldsInjection.propTypes = {
  fields: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string.isRequired,
      type: PropTypes.string.isRequired,
    })
  ),
}

export default AddonSection
