import { type SubscriptionFormData } from '@/lib/api';
import { useFormsCrud } from './use-forms-crud';

export type { SubscriptionFormData };

export function useSubscriptionForms() {
  return useFormsCrud<SubscriptionFormData>({
    endpoint: '/subscription-forms',
    label: 'Subscription form',
  });
}
