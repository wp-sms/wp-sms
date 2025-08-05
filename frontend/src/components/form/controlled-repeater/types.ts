import type { SchemaField, SchemaFieldLayout } from '@/models/settings/types/getGroupSchema';

export type ControlledRepeaterProps = {
    name: string;
    layout?: SchemaFieldLayout;
    fieldGroups: SchemaField['fieldGroups'];
};
