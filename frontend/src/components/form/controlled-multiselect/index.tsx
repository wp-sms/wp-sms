import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledMultiselectProps } from './types';
import { FieldWrapper } from '../field-wrapper';
import { MultiSelect } from '@/components/ui/multiselect';
import { toOptions } from '@/utils/toOptions';

export const ControlledMultiselect: React.FC<ControlledMultiselectProps> = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  options,
  isLoading,
}) => {
  const { control } = useFormContext();

  return (
    <Controller
      name={name ?? ''}
      control={control}
      render={({ field, fieldState }) => {
        return (
          <FieldWrapper
            label={label}
            description={description}
            isLoading={isLoading}
            error={fieldState?.error?.message}
            isLocked={isLocked}
            tag={tag}
            tooltip={tooltip}
          >
            <MultiSelect
              aria-invalid={fieldState?.invalid || !!fieldState?.error}
              value={field?.value ?? []}
              options={toOptions(options) ?? []}
              defaultValue={field?.value ?? []}
              onValueChange={field?.onChange}
            />
          </FieldWrapper>
        );
      }}
    />
  );
};
