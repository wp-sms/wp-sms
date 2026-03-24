import { useFormsCrud } from './use-forms-crud';

export interface RegistrationFormField {
  id: string;
  required: boolean;
  sort_order: number;
}

export interface RegistrationFormData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  status: string;
  fields: RegistrationFormField[];
  auth_overrides: Record<string, Record<string, boolean>>;
  user_role: string;
  redirect_url: string;
  branding: Record<string, string>;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export function useRegistrationForms() {
  return useFormsCrud<RegistrationFormData>({
    endpoint: '/auth/admin/registration-forms',
    label: 'Registration form',
  });
}
