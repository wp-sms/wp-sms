import { Separator } from './separator'
import { SimpleHtmlRenderer } from './simple-html-rendere'

export type HeaderFieldProps = {
  label: string
  description?: string
}

export const HeaderField = ({ label, description }: HeaderFieldProps) => {
  return (
    <div>
      <Separator className="my-4" />

      {description && <SimpleHtmlRenderer htmlContent={description} label={label} />}
    </div>
  )
}
