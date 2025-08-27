'use client';
import { FormProvider as HookFormProvider, type FieldValues } from 'react-hook-form';
import type { FormCmProps } from './types';

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
          event?.preventDefault();
          onSubmit?.(event);
        }}
      >
        {children}
      </form>
    </HookFormProvider>
  );
};
