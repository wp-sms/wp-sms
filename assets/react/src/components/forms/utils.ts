import { SchemaField, FieldOption } from './types'

// Utility to evaluate showIf/hideIf conditions
export function shouldFieldBeVisible(field: SchemaField, formData: Record<string, any>): boolean {
  // If showIf is set, all conditions must match
  if (field.showIf) {
    for (const [depKey, depValue] of Object.entries(field.showIf)) {
      if (formData[depKey] !== depValue) {
        return false
      }
    }
  }
  // If hideIf is set, any match hides the field
  if (field.hideIf) {
    for (const [depKey, depValue] of Object.entries(field.hideIf)) {
      if (formData[depKey] === depValue) {
        return false
      }
    }
  }
  return true
}

// Helper to filter options dynamically
export function getDynamicOptions(field: SchemaField, formData: Record<string, any>): FieldOption | any[] {
  if (!field.options_depends_on) return field.options;
  const depKey = field.options_depends_on;
  const depValue = formData[depKey];
  if (!depValue || depValue.length === 0) return field.options;
  // If options is an array of objects [{value, label}], filter by value
  if (Array.isArray(field.options)) {
    return field.options.filter((opt: any) => depValue.includes(opt.value ?? Object.keys(opt)[0]));
  }
  // If options is an object {value: label}, filter keys
  const filtered: FieldOption = {};
  Object.entries(field.options).forEach(([k, v]) => {
    if (depValue.includes(k)) filtered[k] = v;
  });
  return filtered;
} 