'use client'

import { FormProvider as HookFormProvider, type FieldValues, type FormProviderProps } from 'react-hook-form'
import type { FormEvent, PropsWithChildren } from 'react'

export type FormCmProps<TFieldValues extends FieldValues> = PropsWithChildren<
  {
    onSubmit?: (event?: FormEvent<HTMLFormElement>) => void
    ariaLabel?: string
  } & FormProviderProps<TFieldValues>
>

export const FormProvider = <TFieldValues extends FieldValues>({
  children,
  onSubmit,
  ariaLabel,
  ...props
}: FormCmProps<TFieldValues>) => {
  return (
    <HookFormProvider {...props}>
      <form
        aria-label={ariaLabel}
        onSubmit={(event) => {
          event?.preventDefault()
          onSubmit?.(event)
        }}
      >
        {children}
      </form>
    </HookFormProvider>
  )
}
