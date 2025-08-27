import type { CheckboxProps } from '@radix-ui/react-checkbox';
import type { ControlledFieldProps } from '../field-wrapper/types';

export type ControlledCheckboxProps = {
  name: string;
} & CheckboxProps &
  ControlledFieldProps;
