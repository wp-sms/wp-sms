export type ControlledTextareaProps = React.ComponentProps<'textarea'> & {
    label?: string;
    description?: string;
    isLoading?: boolean;
};
