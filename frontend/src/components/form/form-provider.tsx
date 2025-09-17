'use client'

import type { FormEvent, PropsWithChildren } from 'react'
import { type FieldValues, FormProvider as HookFormProvider, type FormProviderProps } from 'react-hook-form'

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
