export type Country = {
    id: number;
    name: string;
    nativeName: string;
    code: string;
    dialCode: string;
    allDialCodes: string[];
    emoji: string;
    unicode: string;
    flag: string;
};
export type ControlledPhoneProps = React.ComponentProps<'input'> & {
    label?: string;
    description?: string;
    isLoading?: boolean;
};
