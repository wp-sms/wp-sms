import type { FormEvent, PropsWithChildren } from 'react';
import type { FieldValues, FormProviderProps } from 'react-hook-form';

export type FormCmProps<TFieldValues extends FieldValues> = PropsWithChildren<
  {
    onSubmit?: (event?: FormEvent<HTMLFormElement>) => void;
    ariaLabel?: string;
  } & FormProviderProps<TFieldValues>
>;
