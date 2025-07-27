import type { CheckboxProps } from '@radix-ui/react-checkbox';

export type ControlledCheckboxProps = {
    name: string;
    label?: string;
    description?: string;
    isLoading?: boolean;
} & CheckboxProps;
