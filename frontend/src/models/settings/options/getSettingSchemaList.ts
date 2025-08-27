import { clientRequest } from '@/core/config';
import { queryOptions } from '@tanstack/react-query';
import type { UseGetSettingSchemaListType } from '../types';

export function getSettingSchemaListOptions() {
  return queryOptions({
    queryKey: ['schema-list'],
    queryFn: async () => {
      const url = '/settings/schema/list';

      const response = await clientRequest.get<UseGetSettingSchemaListType['response']>(url);

      return response.data;
    },
  });
}
