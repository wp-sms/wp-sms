import type { SchemaFieldType } from '@/models/settings/types/getGroupSchema';
import { ControlledInput } from '../controlled-input';
import type { ControlledFieldRendererProps } from './types';
import { ControlledSelect } from '../controlled-select';
import { ControlledCheckbox } from '../controlled-checkbox';
import { SimpleHtmlRenderer } from '@/components/ui/simple-html-renderer';
import { useFormContext, useWatch } from 'react-hook-form';
import { useEffect } from 'react';
import { useDebounce } from '@/core/hooks/useDebounce';

export const ControlledFieldRenderer: React.FC<ControlledFieldRendererProps> = ({ schema, isLoading = false }) => {
    const FieldComponentMap: Record<SchemaFieldType, React.FC<any>> = {
        text: ControlledInput,
        number: ControlledInput,
        select: ControlledSelect,
        checkbox: ControlledCheckbox,
        advancedselect: ControlledSelect,
        multiselect: ControlledInput,
        html: SimpleHtmlRenderer,
        repeater: ControlledInput,
    };

    const fieldValue = useWatch({ name: schema?.key, exact: true });

    const { formState } = useFormContext();

    const debouncedFieldValue = useDebounce(fieldValue, 1000);

    useEffect(() => {
        const defaultValue = formState?.defaultValues?.[schema?.key];

        if (!schema.auto_save_and_refresh && defaultValue !== fieldValue) {
            console.log('saved');
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
