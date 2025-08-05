import { Input } from '@/components/ui/input';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledNumberInputProps } from './types';
import { FieldWrapper } from '../field-wrapper';

export const ControlledNumberInput: React.FC<ControlledNumberInputProps> = ({
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
                        <Input
                            type="number"
                            aria-invalid={!!fieldState?.invalid || !!fieldState?.error?.message}
                            {...field}
                            {...props}
                        />
                    </FieldWrapper>
                );
            }}
        />
    );
};
