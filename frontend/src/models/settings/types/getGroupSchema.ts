import type { UseQueryOptions } from '@tanstack/react-query';

type GetGroupSchemaParams = {
    params?: Partial<{
        groupName: string;
    }>;
};

export type FieldOption = {
    [key: string]: string | { [key: string]: string };
};

export type SchemaFieldType =
    | 'select'
    | 'checkbox'
    | 'advancedselect'
    | 'text'
    | 'number'
    | 'multiselect'
    | 'repeater'
    | 'html'
    | 'tel'
    | 'countryselect'
    | 'textarea';

export type SchemaField = {
    key: string;
    type: SchemaFieldType;
    label: string;
    description: string;
    groupLabel: string;
    section: string | null;
    options: FieldOption;
    order: number;
    doc: string;
    showIf: { [key: string]: string } | null;
    hideIf: { [key: string]: string } | null;
    repeatable: boolean;
    placeholder?: string;
    fieldGroups?: any[];
    auto_save_and_refresh: boolean;
    default: unknown;
    hidden: boolean;
    min: number | null;
    max: number | null;
    options_depends_on: null;
    readonly: boolean;
};

export type SchemaSection = {
    fields: SchemaField[];
    helpUrl: string;
    id: string;
    layout: string;
    order: number;
    readOnly: boolean;
    subtitle: string;
    title: string;
};

export type GroupSchema = {
    icon: string;
    label: string;
    sections: SchemaSection[];
};

export type GetGroupSchemaResponse = {
    data: GroupSchema | null;
};

export type UseGetGroupSchemaType = {
    options: Partial<UseQueryOptions<any, any, GetGroupSchemaResponse, any>> & GetGroupSchemaParams;
    response: GetGroupSchemaResponse;
};
