import React from 'react'
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { HtmlDescription } from "../html-description"
import { TagBadge } from "../tag-badge"
import { SearchableSelect } from "../searchable-select"
import { FieldRendererProps } from '../types'
import { getDynamicOptions } from '../utils'

export function SelectField({ field, value, onChange, error }: FieldRendererProps) {
  const { key, label, description, readonly, tag } = field

  return (
    <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
      <div className="flex items-center gap-2">
        <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
        {readonly && (
          <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
            Read Only
          </Badge>
        )}
        {tag && <TagBadge tag={tag} />}
      </div>
      <SearchableSelect
        options={field.options}
        value={value || ''}
        onValueChange={onChange}
        placeholder="Select an option"
        searchPlaceholder="Search options..."
        disabled={readonly}
      />
      {description && (
        <div className={readonly ? 'opacity-70' : ''}>
          <HtmlDescription content={description} />
        </div>
      )}
      {error && (
        <p className="text-sm text-destructive mt-1">
          {error}
        </p>
      )}
    </div>
  )
} 