import { clientRequest } from '@/core/config';
import { useMutation } from '@tanstack/react-query';
import type { UseSaveSettingsValuesType } from '../types';

export function useSaveSettingsValues(options?: UseSaveSettingsValuesType['options']) {
    return useMutation({
        mutationFn: async (body: UseSaveSettingsValuesType['body']) => {
            const url = '/settings/save';

            const response = await clientRequest.put<UseSaveSettingsValuesType['response']>(url, body);

            return response.data;
        },
        ...options,
    });
}
