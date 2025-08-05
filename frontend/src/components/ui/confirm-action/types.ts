import type { PropsWithChildren } from 'react';

export type ConfirmActionProps = PropsWithChildren<{
    title: string;
    description: string;
    onConfirm: () => void;
    onCancel?: () => void;
}>;
