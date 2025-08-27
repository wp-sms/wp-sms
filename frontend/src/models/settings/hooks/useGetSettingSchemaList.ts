import { useQuery } from '@tanstack/react-query';
import { getSettingSchemaListOptions } from '../options';

export function useGetSettingSchemaList() {
  return useQuery(getSettingSchemaListOptions());
}
