import type { PropsWithChildren } from 'react';

export type ControlledFieldProps = {
    label?: string;
    description?: string;
    tooltip?: string;
    isPro?: boolean;
    isRequired?: boolean;
    isLocked?: boolean;
    isLoading?: boolean;
    error?: string;
};

export type FieldWrapperProps = PropsWithChildren<ControlledFieldProps>;
