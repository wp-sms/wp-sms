import type { UseQueryOptions } from '@tanstack/react-query';

export type GetGroupValuesParams = {
  params?: Partial<{
    groupName: string;
  }>;
};

export type GroupValues = Record<string, unknown>;

export type GetGroupValuesResponse = {
  success: boolean;
  data: GroupValues;
};

export type UseGetGroupValuesType = {
  options: Partial<UseQueryOptions<any, any, GetGroupValuesResponse, any>> & GetGroupValuesParams;
  response: GetGroupValuesResponse;
};
