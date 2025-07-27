import { useQuery } from '@tanstack/react-query';
import { getGroupSchemaOptions } from '../options/getGroupSchema';
import type { UseGetGroupSchemaType } from '../types/getGroupSchema';

export function useGetGroupSchema(options?: UseGetGroupSchemaType['options']) {
    return useQuery(getGroupSchemaOptions(options));
}
