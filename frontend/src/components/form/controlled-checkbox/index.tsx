import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledCheckboxProps } from './types';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import { FieldLabel } from '../label';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';

export const ControlledCheckbox: React.FC<ControlledCheckboxProps> = ({
    name,
    label,
    description,
    isLoading = false,
    ...props
}) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => {
                return (
                    <div className="flex flex-col gap-y-1.5">
                        <div className="flex items-center gap-x-1.5">
                            <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                                <Checkbox id={name} checked={field.value} onCheckedChange={field.onChange} {...props} />
                            </CustomSkeleton>

                            <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                                <FieldLabel text={label} htmlFor={name} />
                            </CustomSkeleton>
                        </div>

                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldDescription text={description} />
                        </CustomSkeleton>

                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldMessage text={fieldState?.error?.message} />
                        </CustomSkeleton>
                    </div>
                );
            }}
        />
    );
};
