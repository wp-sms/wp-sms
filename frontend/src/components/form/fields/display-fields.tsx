import { AlertCircle } from 'lucide-react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type Props = {
  schema: SchemaField
}

export const HtmlRenderer = ({ schema }: Props) => {
  return schema.options ? (
    <FieldWrapper schema={schema} errors={[]}>
      <div className="font-light text-sm" dangerouslySetInnerHTML={{ __html: schema.options }} />
    </FieldWrapper>
  ) : null
}

export const Header = ({ schema }: Props) => {
  return (
    <FieldWrapper schema={schema} errors={[]}>
      <Separator className="my-2" />
      <div className="font-extrabold text-sm">{schema.groupLabel}</div>
    </FieldWrapper>
  )
}

export const Notice = ({ schema }: Props) => {
  return (
    <Alert variant="default">
      <AlertCircle />
      <AlertTitle>{schema.label}</AlertTitle>
      <AlertDescription>{schema.description}</AlertDescription>
    </Alert>
  )
}
