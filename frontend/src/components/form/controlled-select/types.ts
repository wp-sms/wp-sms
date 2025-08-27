import type { FieldOption } from '@/models/settings/types/getGroupSchema';
import type {
  SelectContentProps,
  SelectGroupProps,
  SelectItemProps,
  SelectProps,
  SelectTriggerProps,
  SelectValueProps,
} from '@radix-ui/react-select';
import type { ControlledFieldProps } from '../field-wrapper/types';

export type ControlledSelectProps = {
  name: string;
  options: FieldOption;
  readOnly?: boolean;
  disabled?: boolean;
  placeholder?: string;
  selectProps?: SelectProps;
  triggerProps?: SelectTriggerProps;
  SelectValueProps?: SelectValueProps;
  SelectContentProps?: SelectContentProps;
  SelectGroupProps?: SelectGroupProps;
  SelectItemProps?: SelectItemProps;
} & ControlledFieldProps;
