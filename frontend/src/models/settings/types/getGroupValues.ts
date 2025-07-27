import type { UseQueryOptions } from '@tanstack/react-query';

export type GetGroupValuesParams = {
    params?: Partial<{
        groupName: string;
    }>;
};

export type GetGroupValuesResponse = {
    success: boolean;
    data: Record<string, unknown>;
};

export type UseGetGroupValuesType = {
    options: Partial<UseQueryOptions<any, any, GetGroupValuesResponse, any>> & GetGroupValuesParams;
    response: GetGroupValuesResponse;
};
