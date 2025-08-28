import type { CheckboxProps } from '@radix-ui/react-checkbox';
import type { ControlledFieldProps } from '../field-wrapper/types';
import type { SchemaField } from '@/models/settings/types/getGroupSchema';

export type ControlledCheckboxProps = {
    name: string;
    sub_fields?: SchemaField[]; // Changed from subFields to sub_fields
    onSubFieldsClick?: (fieldName: string, subFields: SchemaField[]) => void;
} & CheckboxProps &
    ControlledFieldProps;
