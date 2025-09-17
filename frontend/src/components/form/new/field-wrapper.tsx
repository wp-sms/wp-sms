import clsx from 'clsx'
import type { PropsWithChildren } from 'react'

import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { TagBadge } from '@/components/ui/tag-badge'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldDescription } from '../description'

export const FieldWrapper = ({
  field,
  fieldState,
  children,
}: PropsWithChildren<{
  field: SchemaField
  fieldState: { errors: string[] }
}>) => {
  return (
    <div className={clsx('flex flex-col gap-1.5', field.readonly && 'opacity-70')}>
      <div className="flex items-center gap-2">
        {field.type !== 'checkbox' && (
          <Label className={clsx(!!fieldState.errors.length && 'text-destructive')} htmlFor={field.key}>
            {field.label}
          </Label>
        )}

        {field.readonly && (
          <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
            Read Only
          </Badge>
        )}

        {field.tag && <TagBadge tag={field.tag} />}
      </div>

      <div className="flex gap-1.5 flex-col">
        <div className={clsx(field.readonly && 'pointer-events-none', field.type === 'checkbox' && 'flex gap-2')}>
          {children}
          {field.type === 'checkbox' && (
            <Label className={clsx(!!fieldState.errors.length && 'text-destructive')} htmlFor={field.key}>
              {field.label}
            </Label>
          )}
        </div>

        <FieldDescription text={field.description} />

        {!!fieldState.errors.length && (
          <p className="text-xs font-normal text-destructive">{fieldState.errors.join('. ')}</p>
        )}
      </div>
    </div>
  )
}
