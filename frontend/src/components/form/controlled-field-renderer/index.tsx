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
import { useSaveSettingsValues } from '@/models/settings';
import { ControlledMultiselect } from '../controlled-multiselect';
import { ControlledColor } from '../controlled-color';
import { ControlledRepeater } from '../controlled-repeater';
import { HeaderField } from '@/components/ui/header-field';
import { NoticeField } from '@/components/ui/notice-field';
import { ControlledImage } from '../controlled-image';

export const ControlledFieldRenderer: React.FC<ControlledFieldRendererProps> = ({ schema, isLoading = false }) => {
    const FieldComponentMap: Record<SchemaFieldType, React.FC<any>> = {
        text: ControlledInput,
        textarea: ControlledTextarea,
        number: ControlledNumberInput,

        select: ControlledSelect,
        advancedselect: ControlledSelect,
        countryselect: ControlledSelect,

        multiselect: ControlledMultiselect,

        checkbox: ControlledCheckbox,
        html: SimpleHtmlRenderer,
        repeater: ControlledRepeater,
        tel: ControlledPhone,
        color: ControlledColor,

        header: HeaderField,
        notice: NoticeField,

        image: ControlledImage,
    };

    const fieldValue = useWatch({ name: schema?.key, exact: true });

    const { formState } = useFormContext();

    const debouncedFieldValue = useDebounce(fieldValue, 500);

    const saveSettings = useSaveSettingsValues();

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
            htmlContent={
                schema?.type === 'html' && !schema?.description && typeof schema?.options === 'string'
                    ? schema?.options
                    : schema?.description
            }
            isLoading={isLoading}
            isLocked={schema?.readonly}
            tag={schema?.tag}
            isRequired={false}
            fieldGroups={schema?.fieldGroups}
        />
    );
};
