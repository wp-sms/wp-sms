import type { FieldOption } from '@/models/settings/types/getGroupSchema';
import type { ControlledFieldProps } from '../field-wrapper/types';

export type ControlledMultiselectProps = {
    options: FieldOption;
    value: string[];
    name: string;
} & ControlledFieldProps;
