import clsx from 'clsx'

import { Label } from '@/components/ui/label'

export type FieldLabelProps = {
  text?: string
  htmlFor?: string
  isInvalid?: boolean
}

export const FieldLabel = ({ text, htmlFor, isInvalid = false }: FieldLabelProps) => {
  if (!text) {
    return null
  }

  return (
    <Label className={clsx('text-xs font-normal', isInvalid && 'text-destructive')} htmlFor={htmlFor}>
      {text}
    </Label>
  )
}
