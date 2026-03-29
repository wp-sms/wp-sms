import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from 'react';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Trash2, Plus, Search } from 'lucide-react';
import { FIELD_TYPES, FIELD_VISIBILITY, formatLabel } from '@/lib/constants';
import { getMetaKeys } from '@/lib/api';
import type { ProfileFieldDefinition, MetaKeyInfo } from '@/lib/api';

interface ProfileFieldPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: 'create' | 'meta' | 'edit';
  field: ProfileFieldDefinition | null;
  existingIds: string[];
  onSave: (field: ProfileFieldDefinition) => void;
}

const EMPTY_FIELD: ProfileFieldDefinition = {
  id: '',
  type: 'text',
  label: '',
  source: 'custom',
  meta_key: '',
  visibility: 'both',
  required: false,
  sort_order: 99,
  placeholder: '',
  options: [],
  description: '',
  default_value: '',
};

function slugify(str: string): string {
  return str.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

export function ProfileFieldPanel({ open, onOpenChange, mode, field, existingIds, onSave }: ProfileFieldPanelProps) {
  const [form, setForm] = useState<ProfileFieldDefinition>(EMPTY_FIELD);
  const [metaKeys, setMetaKeys] = useState<MetaKeyInfo[]>([]);
  const [metaSearch, setMetaSearch] = useState('');
  const [metaLoading, setMetaLoading] = useState(false);
  const [selectedMeta, setSelectedMeta] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      if (mode === 'edit' && field) {
        setForm(field);
      } else {
        setForm(EMPTY_FIELD);
        setSelectedMeta(null);
        setMetaSearch('');
      }

      if (mode === 'meta') {
        setMetaLoading(true);
        getMetaKeys()
          .then(setMetaKeys)
          .catch(() => setMetaKeys([]))
          .finally(() => setMetaLoading(false));
      }
    }
  }, [open, mode, field]);

  function update(partial: Partial<ProfileFieldDefinition>) {
    setForm((prev) => {
      const next = { ...prev, ...partial };
      // Auto-generate id from label when creating.
      if (mode === 'create' && partial.label !== undefined && !field) {
        next.id = slugify(partial.label);
        next.meta_key = next.id;
      }
      return next;
    });
  }

  function addOption() {
    update({ options: [...(form.options ?? []), { value: '', label: '' }] });
  }

  function updateOption(index: number, key: 'value' | 'label', val: string) {
    const opts = [...(form.options ?? [])];
    opts[index] = { ...opts[index], [key]: val };
    // Auto-fill value from label if value is empty.
    if (key === 'label' && !opts[index].value) {
      opts[index].value = slugify(val);
    }
    update({ options: opts });
  }

  function removeOption(index: number) {
    update({ options: (form.options ?? []).filter((_, i) => i !== index) });
  }

  function handleSelectMeta(mk: MetaKeyInfo) {
    setSelectedMeta(mk.key);
    setForm({
      ...EMPTY_FIELD,
      id: mk.key,
      label: formatLabel(mk.key),
      source: 'meta',
      meta_key: mk.key,
    });
  }

  function handleSubmit() {
    if (!form.id || !form.label) return;
    onSave(form);
  }

  const isSystem = form.source === 'system';
  const idConflict = !field && existingIds.includes(form.id);
  const filteredMeta = metaKeys.filter((mk) =>
    mk.key.toLowerCase().includes(metaSearch.toLowerCase()),
  );

  const title = mode === 'edit'
    ? sprintf(__('Edit: %s', 'wp-sms'), form.label)
    : mode === 'meta'
      ? __('Pick Existing Meta Key', 'wp-sms')
      : __('Create Custom Field', 'wp-sms');

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="h-full w-[420px] rounded-none border-s" aria-describedby="field-panel-desc">
        <DrawerHeader>
          <DrawerTitle>{title}</DrawerTitle>
          <DrawerDescription id="field-panel-desc">
            {mode === 'meta'
              ? __('Browse existing user meta keys from other plugins and add them to your auth forms.', 'wp-sms')
              : isSystem
                ? __('Configure visibility and requirements for this system field.', 'wp-sms')
                : __('Define a custom field for registration and profile forms.', 'wp-sms')}
          </DrawerDescription>
        </DrawerHeader>

        <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-6">
          {/* Meta key picker */}
          {mode === 'meta' && !selectedMeta && (
            <div className="space-y-3">
              <div className="relative">
                <Search className="absolute start-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={__('Search meta keys...', 'wp-sms')}
                  value={metaSearch}
                  onInput={(e) => setMetaSearch((e.target as HTMLInputElement).value)}
                  className="ps-9"
                />
              </div>
              {metaLoading ? (
                <p className="text-sm text-muted-foreground">{__('Loading...', 'wp-sms')}</p>
              ) : (
                <div className="max-h-[400px] overflow-y-auto rounded-lg border divide-y">
                  {filteredMeta.map((mk) => (
                    <button
                      key={mk.key}
                      type="button"
                      className="w-full text-start px-3 py-2.5 hover:bg-muted/50 transition-colors"
                      onClick={() => handleSelectMeta(mk)}
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-sm font-medium font-mono">{mk.key}</span>
                        <span className="text-xs text-muted-foreground">{sprintf(__('%d users', 'wp-sms'), mk.count)}</span>
                      </div>
                      {mk.sample_value && (
                        <p className="text-xs text-muted-foreground mt-0.5 truncate">
                          {sprintf(__('e.g. %s', 'wp-sms'), mk.sample_value)}
                        </p>
                      )}
                    </button>
                  ))}
                  {filteredMeta.length === 0 && (
                    <p className="px-3 py-4 text-sm text-muted-foreground text-center">{__('No meta keys found.', 'wp-sms')}</p>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Field form (create, edit, or meta after selection) */}
          {(mode !== 'meta' || selectedMeta) && (
            <>
              {/* Label */}
              <Field>
                <FieldLabel>{__('Label *', 'wp-sms')}</FieldLabel>
                <Input
                  value={form.label}
                  onInput={(e) => update({ label: (e.target as HTMLInputElement).value })}
                  placeholder={__('e.g. Company', 'wp-sms')}
                  disabled={isSystem}
                />
              </Field>

              {/* ID / Meta Key (read-only for system) */}
              {!isSystem && (
                <Field>
                  <FieldLabel>{__('Field ID / Meta Key *', 'wp-sms')}</FieldLabel>
                  <Input
                    dir="ltr"
                    value={form.meta_key}
                    onInput={(e) => update({ meta_key: (e.target as HTMLInputElement).value, id: (e.target as HTMLInputElement).value })}
                    placeholder="company"
                    disabled={mode === 'edit'}
                  />
                  {idConflict && (
                    <p className="text-xs text-destructive">{__('This ID is already in use.', 'wp-sms')}</p>
                  )}
                  <FieldDescription>
                    {__('The wp_usermeta key where the value is stored.', 'wp-sms')}
                  </FieldDescription>
                </Field>
              )}

              {/* Type (not for system) */}
              {!isSystem && (
                <Field>
                  <FieldLabel>{__('Field Type', 'wp-sms')}</FieldLabel>
                  <RadioGroup
                    value={form.type}
                    onValueChange={(v) => update({ type: v as ProfileFieldDefinition['type'] })}
                    className="grid grid-cols-2 gap-2"
                  >
                    {FIELD_TYPES.map((ft) => (
                      <label key={ft.value} className="flex items-center gap-2 rounded-md border px-3 py-2 cursor-pointer hover:bg-muted/50 has-[data-state=checked]:border-primary">
                        <RadioGroupItem value={ft.value} />
                        <span className="text-sm">{ft.label}</span>
                      </label>
                    ))}
                  </RadioGroup>
                </Field>
              )}

              {/* Select options */}
              {form.type === 'select' && !isSystem && (
                <Field>
                  <FieldLabel>{__('Options', 'wp-sms')}</FieldLabel>
                  <div className="space-y-2">
                    {(form.options ?? []).map((opt, i) => (
                      <div key={i} className="flex items-center gap-2">
                        <Input
                          placeholder={__('Label', 'wp-sms')}
                          value={opt.label}
                          onInput={(e) => updateOption(i, 'label', (e.target as HTMLInputElement).value)}
                          className="flex-1"
                        />
                        <Input
                          placeholder={__('Value', 'wp-sms')}
                          value={opt.value}
                          onInput={(e) => updateOption(i, 'value', (e.target as HTMLInputElement).value)}
                          className="w-28"
                        />
                        <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={() => removeOption(i)}>
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    ))}
                    <Button variant="outline" size="sm" onClick={addOption}>
                      <Plus className="me-1 h-3.5 w-3.5" />
                      {__('Add Option', 'wp-sms')}
                    </Button>
                  </div>
                </Field>
              )}

              {/* Placeholder (text/textarea only) */}
              {(form.type === 'text' || form.type === 'textarea') && !isSystem && (
                <Field>
                  <FieldLabel>{__('Placeholder', 'wp-sms')}</FieldLabel>
                  <Input
                    value={form.placeholder ?? ''}
                    onInput={(e) => update({ placeholder: (e.target as HTMLInputElement).value })}
                    placeholder={__('Optional placeholder text', 'wp-sms')}
                  />
                </Field>
              )}

              {/* Help Text */}
              {!isSystem && (
                <Field>
                  <FieldLabel>{__('Help Text', 'wp-sms')}</FieldLabel>
                  <Input
                    value={form.description ?? ''}
                    onInput={(e) => update({ description: (e.target as HTMLInputElement).value })}
                    placeholder={__('Shown below the field on the form', 'wp-sms')}
                  />
                  <FieldDescription>{__('Persistent guidance displayed below the input.', 'wp-sms')}</FieldDescription>
                </Field>
              )}

              {/* Default Value */}
              {!isSystem && (
                <Field>
                  <FieldLabel>{__('Default Value', 'wp-sms')}</FieldLabel>
                  {form.type === 'select' ? (
                    <select
                      value={String(form.default_value ?? '')}
                      onChange={(e) => update({ default_value: e.target.value })}
                      className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                      <option value="">{__('No default', 'wp-sms')}</option>
                      {(form.options ?? []).map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                    </select>
                  ) : form.type === 'checkbox' ? (
                    <label className="flex items-center gap-2 cursor-pointer">
                      <Checkbox
                        checked={!!form.default_value}
                        onCheckedChange={(checked) => update({ default_value: !!checked })}
                      />
                      <span className="text-sm">{__('Checked by default', 'wp-sms')}</span>
                    </label>
                  ) : (
                    <Input
                      value={String(form.default_value ?? '')}
                      onInput={(e) => update({ default_value: (e.target as HTMLInputElement).value })}
                      placeholder={__('Pre-filled value for new registrations', 'wp-sms')}
                    />
                  )}
                  <FieldDescription>{__("Value used when the user doesn't provide one.", 'wp-sms')}</FieldDescription>
                </Field>
              )}

              <Separator />

              {/* Visibility */}
              <Field>
                <FieldLabel>{__('Visibility', 'wp-sms')}</FieldLabel>
                <RadioGroup
                  value={form.visibility}
                  onValueChange={(v) => update({ visibility: v as ProfileFieldDefinition['visibility'] })}
                >
                  {FIELD_VISIBILITY
                    .filter((fv) => {
                      if (fv.value !== 'hidden') return true;
                      return form.id !== 'email' && form.id !== 'password';
                    })
                    .map((fv) => (
                    <label key={fv.value} className="flex items-center gap-2 cursor-pointer">
                      <RadioGroupItem value={fv.value} />
                      <span className="text-sm">{fv.label}</span>
                    </label>
                  ))}
                </RadioGroup>
              </Field>

              {/* Required */}
              <label className="flex items-center gap-2 cursor-pointer">
                <Checkbox
                  checked={form.required}
                  onCheckedChange={(checked) => update({ required: !!checked })}
                />
                <span className="text-sm">{__('Required', 'wp-sms')}</span>
              </label>

              <Separator />

              <Button
                className="w-full"
                onClick={handleSubmit}
                disabled={!form.id || !form.label || idConflict}
              >
                {mode === 'edit' ? __('Save Changes', 'wp-sms') : __('Add Field', 'wp-sms')}
              </Button>
            </>
          )}
        </div>
      </DrawerContent>
    </Drawer>
  );
}
