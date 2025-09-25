import { CloudUpload } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { useWordPressMediaUploader } from '@/hooks/use-wordpress-media-uploader'
import type { FieldValue } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type ImageFieldProps = {
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
}

export const ImageField = ({ fieldApi, fieldValue }: ImageFieldProps) => {
  const { openMediaUploader } = useWordPressMediaUploader()

  return (
    <Button variant="outline" type="button" onClick={() => openMediaUploader(fieldApi.handleChange)}>
      <CloudUpload />
      {fieldValue ? 'Change Image' : 'Select Image'}
    </Button>
  )
}
