import clsx from 'clsx'
import { type PropsWithChildren } from 'react'

import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { TagBadge } from '@/components/ui/tag-badge'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldDescription } from './description'

export const FieldWrapper = ({
  schema,
  errors,
  children,
}: PropsWithChildren<{
  schema: SchemaField
  errors: string[]
}>) => {
  return (
    <div className={clsx('flex flex-col gap-1.5', schema.readonly && 'opacity-70')}>
      <div className="flex items-center gap-2">
        {schema.type !== 'checkbox' && (
          <Label className={clsx(!!errors.length && 'text-destructive')} htmlFor={schema.key}>
            {schema.label}
          </Label>
        )}

        {schema.readonly && (
          <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
            Read Only
          </Badge>
        )}

        {schema.tag && <TagBadge tag={schema.tag} />}
      </div>

      <div className="flex gap-1.5 flex-col">
        <div className={clsx(schema.readonly && 'pointer-events-none', schema.type === 'checkbox' && 'flex gap-2')}>
          {children}
          {schema.type === 'checkbox' && (
            <Label className={clsx(!!errors.length && 'text-destructive')} htmlFor={schema.key}>
              {schema.label}
            </Label>
          )}
        </div>

        <FieldDescription text={schema.description} />

        {!!errors.length && <p className="text-xs font-normal text-destructive">{errors.join('. ')}</p>}
      </div>
    </div>
  )
}
