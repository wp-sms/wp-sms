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
import { toOptions } from '@/utils/toOptions';
import { FieldWrapper } from '../field-wrapper';

export const ControlledSelect: React.FC<ControlledSelectProps> = ({
  name,
  options,
  placeholder,
  readOnly,
  disabled,
  SelectContentProps,
  SelectGroupProps,
  SelectItemProps,
  SelectValueProps,
  selectProps,
  triggerProps,
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
      control={control}
      name={name}
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
            <Select
              disabled={disabled || readOnly}
              onValueChange={field.onChange}
              defaultValue={field.value}
              value={field.value}
              {...selectProps}
            >
              <SelectTrigger
                aria-invalid={!!fieldState?.invalid || !!fieldState?.error}
                aria-disabled={disabled || readOnly}
                aria-readonly={readOnly}
                className="w-full"
                {...triggerProps}
              >
                <SelectValue id={name} className="w-full" placeholder={placeholder} {...SelectValueProps} />
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
                    <SelectItem key={`select-item-${item.value}`} value={item?.value} {...SelectItemProps}>
                      {item.value}
                    </SelectItem>
                  );
                })}
              </SelectContent>
            </Select>
          </FieldWrapper>
        );
      }}
    />
  );
};
