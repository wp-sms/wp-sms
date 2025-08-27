import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledColorProps } from './types';
import { Input } from '@/components/ui/input';
import { FieldWrapper } from '../field-wrapper';

export const ControlledColor: React.FC<ControlledColorProps> = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
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
            <div className="flex items-center gap-2">
              <Input type="color" {...field} className="w-12 h-10 p-1 border rounded cursor-pointer" />

              <Input type="text" {...field} className="flex-1" placeholder="#ff6b35" />
            </div>
          </FieldWrapper>
        );
      }}
    />
  );
};
