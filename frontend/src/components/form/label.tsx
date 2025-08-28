import { Label } from '@/components/ui/label'
import clsx from 'clsx'

export type FieldLabelProps = {
  text?: string
  htmlFor?: string
  isInvalid?: boolean
}

export const FieldLabel: React.FC<FieldLabelProps> = ({ text, htmlFor, isInvalid = false }) => {
  if (!text) {
    return null
  }

  return (
    <Label className={clsx('text-xs font-normal', isInvalid && 'text-destructive')} htmlFor={htmlFor}>
      {text}
    </Label>
  )
}
