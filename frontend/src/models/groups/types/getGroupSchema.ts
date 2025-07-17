import type { UseQueryOptions } from '@tanstack/react-query';

type GetGroupSchemaParams = {
    params?: Partial<{
        groupName: string;
    }>;
};

type GetGroupSchemaResponse = unknown;

export type UseGetGroupSchemaType = {
    options: Partial<UseQueryOptions<any, any, GetGroupSchemaResponse, any>> & GetGroupSchemaParams;
    response: GetGroupSchemaResponse;
};
