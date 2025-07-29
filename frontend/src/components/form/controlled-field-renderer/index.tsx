import type { SchemaFieldType } from '@/models/settings/types/getGroupSchema';
import { ControlledInput } from '../controlled-input';
import type { ControlledFieldRendererProps } from './types';
import { ControlledSelect } from '../controlled-select';
import { ControlledCheckbox } from '../controlled-checkbox';
import { SimpleHtmlRenderer } from '@/components/ui/simple-html-renderer';
import { useFormContext, useWatch } from 'react-hook-form';
import { useEffect } from 'react';
import { useDebounce } from '@/core/hooks/useDebounce';
import { ControlledPhone } from '../controlled-phone';
import { ControlledTextarea } from '../controlled-textarea';
import { ControlledNumberInput } from '../controlled-number-input';
import { useQueryClient } from '@tanstack/react-query';
import { getGroupValuesOptions, useSaveSettingsValues } from '@/models/settings';

export const ControlledFieldRenderer: React.FC<ControlledFieldRendererProps> = ({ schema, isLoading = false }) => {
    const FieldComponentMap: Record<SchemaFieldType, React.FC<any>> = {
        text: ControlledInput,
        textarea: ControlledTextarea,
        number: ControlledNumberInput,

        select: ControlledSelect,
        advancedselect: ControlledSelect,
        countryselect: ControlledSelect,

        multiselect: ControlledInput,

        checkbox: ControlledCheckbox,
        html: SimpleHtmlRenderer,
        repeater: ControlledInput,
        tel: ControlledPhone,
    };

    const fieldValue = useWatch({ name: schema?.key, exact: true });

    const { formState, setValue } = useFormContext();

    const debouncedFieldValue = useDebounce(fieldValue, 500);

    const queryClient = useQueryClient();

    const saveSettings = useSaveSettingsValues({
        onSuccess: (response, variables) => {
            for (const field in variables) {
                queryClient.setQueryData(
                    getGroupValuesOptions({ params: { groupName: name ?? 'general' } }).queryKey,
                    (old: any) => {
                        return {
                            ...old,
                            [field]: variables[field],
                        };
                    }
                );

                setValue(field, variables[field]);
            }
        },
    });

    useEffect(() => {
        const defaultValue = formState?.defaultValues?.[schema?.key];

        if (schema.auto_save_and_refresh && defaultValue !== fieldValue) {
            saveSettings.mutate({ [schema?.key]: fieldValue });
        }
    }, [schema?.key, debouncedFieldValue, formState?.defaultValues]);

    const FieldComponent = FieldComponentMap?.[schema?.type];

    if (!FieldComponent) {
        return null;
    }

    return (
        <FieldComponent
            name={schema?.key}
            label={schema?.label}
            options={schema?.options}
            description={schema?.description}
            disabled={schema?.readonly}
            htmlContent={schema?.description}
            isLoading={isLoading}
        />
    );
};
