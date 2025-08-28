import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledCheckboxProps } from './types';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldWrapper } from '../field-wrapper';
import { Settings } from 'lucide-react';

export const ControlledCheckbox: React.FC<ControlledCheckboxProps> = ({
    name,
    label,
    description,
    tooltip,
    tag,
    isLocked,
    isLoading,
    sub_fields,
    onSubFieldsClick,
    ...props
}) => {
    const { control } = useFormContext();

    const handleSettingsClick = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (sub_fields && sub_fields.length > 0 && onSubFieldsClick) {
            onSubFieldsClick(name, sub_fields);
        }
    };

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
                        <div className="flex items-center gap-2">
                            <Checkbox id={name} checked={field.value} onCheckedChange={field.onChange} {...props} />
                            {sub_fields && sub_fields.length > 0 && (
                                <button
                                    type="button"
                                    onClick={handleSettingsClick}
                                    className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                    title="Configure settings"
                                >
                                    <Settings size={16} />
                                </button>
                            )}
                        </div>
                    </FieldWrapper>
                );
            }}
        />
    );
};
