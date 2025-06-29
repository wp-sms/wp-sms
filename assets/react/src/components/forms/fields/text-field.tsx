import React from 'react'
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { HtmlDescription } from "../html-description"
import { TagBadge } from "../tag-badge"
import { FieldRendererProps } from '../types'

export function TextField({ field, value, onChange, error }: FieldRendererProps) {
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
      <Input
        id={key}
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        disabled={readonly}
        placeholder={field.placeholder}
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