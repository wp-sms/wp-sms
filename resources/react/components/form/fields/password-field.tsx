import { __ } from '@wordpress/i18n'
import { useStore } from '@tanstack/react-form'
import { Eye, EyeOff } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type PasswordFieldProps = {
  schema: SchemaField
}

export const PasswordField = ({ schema }: PasswordFieldProps) => {
  const field = useFieldContext<string>()
  const [showPassword, setShowPassword] = useState(false)

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <div className="relative">
        <Input
          id={schema.key}
          type={showPassword ? 'text' : 'password'}
          placeholder={schema.placeholder}
          defaultValue={String(schema.default || '')}
          value={String(field.state.value || '')}
          onBlur={field.handleBlur}
          onChange={(e) => field.handleChange(e.target.value)}
          disabled={schema.readonly}
          aria-invalid={!!errors.length}
          className="pr-10"
        />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
          onClick={() => setShowPassword(!showPassword)}
          disabled={schema.readonly}
        >
          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          <span className="sr-only">
            {showPassword ? __('Hide password', 'wp-sms') : __('Show password', 'wp-sms')}
          </span>
        </Button>
      </div>
    </FieldWrapper>
  )
}
