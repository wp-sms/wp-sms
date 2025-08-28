import { FieldLabel } from '@/components/form/label'
import DOMPurify from 'dompurify'

export type SimpleHtmlRendererProps = {
  label?: string
  name?: string
  htmlContent?: string
}

export const SimpleHtmlRenderer = ({ htmlContent, label, name }: SimpleHtmlRendererProps) => {
  const sanitizedHTML = DOMPurify.sanitize(htmlContent ?? '')

  return (
    <div className="flex flex-col gap-y-1.5">
      <FieldLabel text={label} htmlFor={name} />

      {htmlContent && <div dangerouslySetInnerHTML={{ __html: sanitizedHTML }} />}
    </div>
  )
}
