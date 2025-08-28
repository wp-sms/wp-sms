import type { SchemaField } from '@/models/settings/types/getGroupSchema';

export type ControlledFieldRendererProps = {
    schema: SchemaField;
    isLoading?: boolean;
    onSubFieldsClick?: (fieldName: string, subFields: SchemaField[]) => void;
};
