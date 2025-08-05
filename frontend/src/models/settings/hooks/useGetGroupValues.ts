import { useQuery } from '@tanstack/react-query';
import { getGroupValuesOptions } from '../options';
import type { UseGetGroupValuesType } from '../types';

export function useGetGroupValues(options?: UseGetGroupValuesType['options']) {
    return useQuery(getGroupValuesOptions(options));
}
