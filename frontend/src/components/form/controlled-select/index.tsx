import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledSelectProps } from './types';
import { FieldLabel } from '../label';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';

export const ControlledSelect: React.FC<ControlledSelectProps> = ({
    name,
    options,
    label,
    description,
    placeholder,
    readOnly,
    disabled,
    SelectContentProps,
    SelectGroupProps,
    SelectItemProps,
    SelectValueProps,
    selectProps,
    triggerProps,
    isLoading = false,
}) => {
    const { control } = useFormContext();

    return (
        <Controller
            control={control}
            name={name}
            render={({ field, fieldState }) => {
                return (
                    <div className="w-full flex flex-col gap-y-1.5">
                        <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                            <FieldLabel text={label} htmlFor={name} />
                        </CustomSkeleton>

                        <CustomSkeleton isLoading={isLoading}>
                            <Select
                                disabled={disabled || readOnly}
                                onValueChange={field.onChange}
                                defaultValue={field.value}
                                {...selectProps}
                            >
                                <SelectTrigger
                                    aria-invalid={fieldState?.invalid}
                                    aria-disabled={disabled || readOnly}
                                    aria-readonly={readOnly}
                                    className="w-full"
                                    {...triggerProps}
                                >
                                    <SelectValue
                                        id={name}
                                        className="w-full"
                                        placeholder={placeholder}
                                        {...SelectValueProps}
                                    />
                                </SelectTrigger>

                                <SelectContent {...SelectContentProps}>
                                    {Object.entries(options ?? {})?.map(([key, value]) => {
                                        if (typeof value === 'string') {
                                            return (
                                                <SelectItem key={`select-item-${key}`} value={key} {...SelectItemProps}>
                                                    {value}
                                                </SelectItem>
                                            );
                                        }

                                        return (
                                            <SelectGroup key={`select-group-${key}`} {...SelectGroupProps}>
                                                <SelectLabel>{key}</SelectLabel>

                                                {Object.entries(value ?? {})?.map(([k, v]) => {
                                                    return (
                                                        <SelectItem
                                                            key={`group-select-item-${k}`}
                                                            value={String(k)}
                                                            {...SelectItemProps}
                                                        >
                                                            {v}
                                                        </SelectItem>
                                                    );
                                                })}
                                            </SelectGroup>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        </CustomSkeleton>

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
