import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledCheckboxProps } from './types';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldWrapper } from '../field-wrapper';

export const ControlledCheckbox: React.FC<ControlledCheckboxProps> = ({
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
            name={name}
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
                        direction="row"
                    >
                        <Checkbox id={name} checked={field.value} onCheckedChange={field.onChange} {...props} />
                    </FieldWrapper>
                );
            }}
        />
    );
};
