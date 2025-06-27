import React from 'react'
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { HtmlDescription } from "../html-description"
import { TagBadge } from "../tag-badge"
import { SearchableMultiSelect } from "../searchable-multiselect"
import { FieldRendererProps } from '../types'

export function MultiSelectField({ field, value, onChange, error }: FieldRendererProps) {
  const { key, label, description, readonly, tag, sortable } = field

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <Label htmlFor={key}>{label}</Label>
        {tag && <TagBadge tag={tag} />}
      </div>
      <SearchableMultiSelect
        options={field.options}
        value={Array.isArray(value) ? value : []}
        onValueChange={onChange}
        placeholder="Select options"
        searchPlaceholder="Search options..."
        sortable={sortable}
        disabled={readonly}
      />
      {description && (
        <HtmlDescription content={description} />
      )}
      {error && (
        <p className="text-sm text-destructive mt-1">
          {error}
        </p>
      )}
    </div>
  )
} 