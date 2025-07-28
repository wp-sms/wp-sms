import { Input } from '@/components/ui/input';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledNumberInputProps } from './types';
import { FieldLabel } from '../label';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';

export const ControlledNumberInput: React.FC<ControlledNumberInputProps> = ({
    name,
    label,
    description,
    isLoading = false,
    ...props
}) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={name ?? ''}
            control={control}
            render={({ field, fieldState }) => {
                return (
                    <div className="flex flex-col gap-y-1.5">
                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldLabel text={label} />
                        </CustomSkeleton>

                        <CustomSkeleton isLoading={isLoading}>
                            <Input type="number" aria-invalid={fieldState.invalid} {...field} {...props} />
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
