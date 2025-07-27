import type { FieldOption } from '@/models/settings/types/getGroupSchema';
import type {
    SelectContentProps,
    SelectGroupProps,
    SelectItemProps,
    SelectProps,
    SelectTriggerProps,
    SelectValueProps,
} from '@radix-ui/react-select';

export type ControlledSelectProps = {
    name: string;
    options: FieldOption;
    isLoading?: boolean;
    readOnly?: boolean;
    disabled?: boolean;
    label?: string;
    description?: string;
    placeholder?: string;
    selectProps?: SelectProps;
    triggerProps?: SelectTriggerProps;
    SelectValueProps?: SelectValueProps;
    SelectContentProps?: SelectContentProps;
    SelectGroupProps?: SelectGroupProps;
    SelectItemProps?: SelectItemProps;
};
