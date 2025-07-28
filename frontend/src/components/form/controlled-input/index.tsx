import { Input } from '@/components/ui/input';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledInputProps } from './types';
import { FieldLabel } from '../label';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';
import { FieldWrapper } from '../field-wrapper';

export const ControlledInput: React.FC<ControlledInputProps> = ({
    name,
    label,
    description,
    tooltip,
    isPro,
    isRequired,
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
                    >
                        <Input aria-invalid={fieldState.invalid} {...field} {...props} />
                    </FieldWrapper>
                );

                return (
                    <div className="flex flex-col gap-y-1.5">
                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldLabel text={label} />
                        </CustomSkeleton>

                        <CustomSkeleton isLoading={isLoading}>
                            <Input aria-invalid={fieldState.invalid} {...field} {...props} />
                        </CustomSkeleton>

                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldDescription text={description} />
                        </CustomSkeleton>

                        <FieldMessage text={fieldState?.error?.message} />
                    </div>
                );
            }}
        />
    );
};
