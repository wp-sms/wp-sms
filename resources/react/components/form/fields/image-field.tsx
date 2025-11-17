import { useStore } from '@tanstack/react-form'
import { CloudUpload } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { useFieldContext } from '@/context/form-context'
import { useWordPressMediaUploader } from '@/hooks/use-wordpress-media-uploader'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type ImageFieldProps = {
  schema: SchemaField
}

export const ImageField = ({ schema }: ImageFieldProps) => {
  const field = useFieldContext<string>()
  const { openMediaUploader } = useWordPressMediaUploader()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper errors={errors} schema={schema}>
      <Button variant="outline" type="button" onClick={() => openMediaUploader(field.handleChange)}>
        <CloudUpload />
        {field.state.value ? 'Change Image' : 'Select Image'}
      </Button>
    </FieldWrapper>
  )
}
