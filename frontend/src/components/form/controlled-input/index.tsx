import { Input } from '@/components/ui/input';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledInputProps } from './types';
import { FieldWrapper } from '../field-wrapper';

export const ControlledInput: React.FC<ControlledInputProps> = ({
    name,
    label,
    description,
    tooltip,
    tag,
    isLocked,
    isLoading,
    ...props
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
                        <Input aria-invalid={fieldState?.invalid || !!fieldState?.error} {...field} {...props} />
                    </FieldWrapper>
                );
            }}
        />
    );
};
