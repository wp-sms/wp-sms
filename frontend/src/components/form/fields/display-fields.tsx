import { AlertCircle } from 'lucide-react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import type { SchemaField } from '@/types/settings/group-schema'

type HtmlRendererProps = {
  field: SchemaField
}

export const HtmlRenderer = ({ field }: HtmlRendererProps) => {
  return field.options ? (
    <div className="font-light text-sm" dangerouslySetInnerHTML={{ __html: field.options }} />
  ) : null
}

type HeaderProps = {
  field: SchemaField
}

export const Header = ({ field }: HeaderProps) => {
  return (
    <div>
      <Separator className="my-2" />
      <div className="font-extrabold text-sm">{field.groupLabel}</div>
    </div>
  )
}

type NoticeProps = {
  field: SchemaField
}

export const Notice = ({ field }: NoticeProps) => {
  return (
    <Alert variant="default">
      <AlertCircle />
      <AlertTitle>{field.label}</AlertTitle>
      <AlertDescription>{field.description}</AlertDescription>
    </Alert>
  )
}
