export type ControlledInputProps = React.ComponentProps<'input'> & {
    label?: string;
    description?: string;
    isLoading?: boolean;
};
