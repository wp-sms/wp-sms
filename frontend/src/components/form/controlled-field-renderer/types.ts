import type { SchemaField } from '@/models/settings/types/getGroupSchema';

export type ControlledFieldRendererProps = {
    schema: SchemaField;
    isLoading?: boolean;
};
