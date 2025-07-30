import type { PropsWithChildren } from 'react';

export type ControlledFieldProps = {
    label?: string;
    description?: string;
    tooltip?: string;
    isLocked?: boolean;
    isLoading?: boolean;
    error?: string;
    readonly?: boolean;
    tag?: string;
};

export type FieldWrapperProps = PropsWithChildren<ControlledFieldProps & { direction?: 'row' | 'column' }>;
