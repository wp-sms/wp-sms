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
import { toOptions } from '@/utils/toOptions';

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
                                    {toOptions(options)?.map((item) => {
                                        if (item?.children) {
                                            return (
                                                <SelectGroup key={`select-group-${item.value}`} {...SelectGroupProps}>
                                                    <SelectLabel>{item.label}</SelectLabel>

                                                    {item.children?.map((child) => {
                                                        return (
                                                            <SelectItem
                                                                key={`group-select-item-${child.value}`}
                                                                value={String(child.value)}
                                                                {...SelectItemProps}
                                                            >
                                                                {child.label}
                                                            </SelectItem>
                                                        );
                                                    })}
                                                </SelectGroup>
                                            );
                                        }

                                        return (
                                            <SelectItem
                                                key={`select-item-${item.value}`}
                                                value={item.value}
                                                {...SelectItemProps}
                                            >
                                                {item.value}
                                            </SelectItem>
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
