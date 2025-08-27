import { clientRequest } from '@/core/config';
import { queryOptions } from '@tanstack/react-query';
import type { UseGetGroupValuesType } from '../types';

export function getGroupValuesOptions(options?: UseGetGroupValuesType['options']) {
  const { params, ...restOptions } = options ?? {};

  return queryOptions({
    queryKey: ['group-values', params],
    queryFn: async () => {
      const url = `/settings/values/group/${params?.groupName}`;

      const response = await clientRequest.get<UseGetGroupValuesType['response']>(url);

      return response.data;
    },
    enabled: Boolean(params?.groupName),
    ...restOptions,
  });
}
