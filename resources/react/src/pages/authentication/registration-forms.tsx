import { __, sprintf, _n } from '@wordpress/i18n';
import { useState, useEffect } from 'react';
import { PageHeader } from '@/components/layout/page-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { DataTable } from '@/components/ui/data-table';
import { EmptyState } from '@/components/ui/empty-state';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
  DrawerFooter,
} from '@/components/ui/drawer';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { NameCell } from '@/components/ui/name-cell';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Plus, Pencil, Copy, Trash2, ClipboardCopy, FileText, ArrowUp, ArrowDown } from 'lucide-react';
import { useConfirm } from '@/components/confirm-provider';
import { useRegistrationForms, type RegistrationFormData, type RegistrationFormField } from '@/hooks/use-registration-forms';
import { copyToClipboard, generateSlug, getAvailableRoles } from '@/lib/utils';
import { SYSTEM_FIELD_OPTIONS } from '@/lib/constants';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/error-utils';
import { pluralize } from '@/lib/utils';

interface FormEditorState {
  name: string;
  slug: string;
  description: string;
  status: string;
  fields: RegistrationFormField[];
  auth_overrides: Record<string, Record<string, boolean>>;
  user_role: string;
  redirect_url: string;
  branding: Record<string, string>;
}

const EMPTY_FORM: FormEditorState = {
  name: '',
  slug: '',
  description: '',
  status: 'active',
  fields: [
    { id: 'email', required: true, sort_order: 1 },
    { id: 'password', required: true, sort_order: 2 },
  ],
  auth_overrides: {},
  user_role: '',
  redirect_url: '',
  branding: {},
};

export function RegistrationForms() {
  const { forms, loading, create, update, remove, duplicate } = useRegistrationForms();
  const confirm = useConfirm();
  const [panelOpen, setPanelOpen] = useState(false);
  const [editingForm, setEditingForm] = useState<RegistrationFormData | null>(null);
  const [formState, setFormState] = useState<FormEditorState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [slugManual, setSlugManual] = useState(false);

  const isEdit = !!editingForm;

  useEffect(() => {
    if (editingForm) {
      setFormState({
        name: editingForm.name,
        slug: editingForm.slug,
        description: editingForm.description || '',
        status: editingForm.status,
        fields: editingForm.fields,
        auth_overrides: editingForm.auth_overrides,
        user_role: editingForm.user_role,
        redirect_url: editingForm.redirect_url,
        branding: editingForm.branding,
      });
      setSlugManual(true);
    } else {
      setFormState(EMPTY_FORM);
      setSlugManual(false);
    }
  }, [editingForm, panelOpen]);

  function openCreate() {
    setEditingForm(null);
    setPanelOpen(true);
  }

  function openEdit(form: RegistrationFormData) {
    setEditingForm(form);
    setPanelOpen(true);
  }

  async function handleSave() {
    setSaving(true);
    try {
      if (editingForm) {
        await update(editingForm.id, formState);
      } else {
        await create(formState);
      }
      setPanelOpen(false);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Failed to save form'));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    const ok = await confirm({
      title: __('Delete Registration Form', 'wp-sms'),
      description: __('This will permanently delete this registration form. Existing users registered through this form will not be affected.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (ok) {
      await remove(id);
    }
  }

  async function copyShortcode(text: string) {
    await copyToClipboard(text);
    toast.success(__('Shortcode copied to clipboard', 'wp-sms'));
  }

  function copyPopupShortcode(slug: string) {
    copyShortcode(`[wsms_auth id="${slug}" view="register"]`);
  }

  function copyEmbedShortcode(slug: string) {
    copyShortcode(`[wsms_auth id="${slug}" view="register" mode="embed"]`);
  }

  function toggleField(fieldId: string) {
    setFormState((prev) => {
      const exists = prev.fields.find((f) => f.id === fieldId);
      if (exists) {
        return { ...prev, fields: prev.fields.filter((f) => f.id !== fieldId) };
      }
      return {
        ...prev,
        fields: [...prev.fields, { id: fieldId, required: false, sort_order: prev.fields.length + 1 }],
      };
    });
  }

  function toggleFieldRequired(fieldId: string) {
    setFormState((prev) => ({
      ...prev,
      fields: prev.fields.map((f) =>
        f.id === fieldId ? { ...f, required: !f.required } : f
      ),
    }));
  }

  function moveField(fieldId: string, direction: 'up' | 'down') {
    setFormState((prev) => {
      const idx = prev.fields.findIndex((f) => f.id === fieldId);
      if (idx === -1) return prev;
      const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
      if (swapIdx < 0 || swapIdx >= prev.fields.length) return prev;
      const newFields = [...prev.fields];
      [newFields[idx], newFields[swapIdx]] = [newFields[swapIdx], newFields[idx]];
      return {
        ...prev,
        fields: newFields.map((f, i) => ({ ...f, sort_order: i + 1 })),
      };
    });
  }

  const roles = getAvailableRoles();

  return (
    <>
      <div className="space-y-4">
        <PageHeader
          icon={FileText}
          title={__('Registration Forms', 'wp-sms')}
          metadata={pluralize(forms.length, 'form')}
          actions={
            <Button onClick={openCreate} size="sm">
              <Plus className="me-1 h-3.5 w-3.5" />
              {__('Create Form', 'wp-sms')}
            </Button>
          }
        />
        <DataTable
          loading={loading}
          isEmpty={forms.length === 0}
          empty={
            <EmptyState
              icon={FileText}
              title={__('No registration forms yet', 'wp-sms')}
              description={__('Create your first registration form to collect different information for different user types.', 'wp-sms')}
              action={
                <Button onClick={openCreate} size="sm">
                  <Plus className="me-1 h-3.5 w-3.5" />
                  {__('Create Form', 'wp-sms')}
                </Button>
              }
            />
          }
        >
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{__('Name', 'wp-sms')}</TableHead>
                <TableHead>{__('Slug', 'wp-sms')}</TableHead>
                <TableHead>{__('Fields', 'wp-sms')}</TableHead>
                <TableHead>{__('Role', 'wp-sms')}</TableHead>
                <TableHead>{__('Status', 'wp-sms')}</TableHead>
                <TableHead className="w-[70px]" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {forms.map((form) => (
                <TableRow key={form.id} className="even:bg-muted/30">
                  <NameCell onClick={() => openEdit(form)}>{form.name}</NameCell>
                  <TableCell>
                    <code className="text-xs bg-muted px-1.5 py-0.5 rounded">{form.slug}</code>
                  </TableCell>
                  <TableCell>{sprintf(_n('%d field', '%d fields', form.fields.length, 'wp-sms'), form.fields.length)}</TableCell>
                  <TableCell>
                    {form.user_role ? (
                      <Badge variant="outline">{roles.find((r) => r.value === form.user_role)?.label || form.user_role}</Badge>
                    ) : (
                      <span className="text-muted-foreground text-sm">{__('Default', 'wp-sms')}</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant={form.status === 'active' ? 'success' : 'secondary'}>
                      {form.status}
                    </Badge>
                  </TableCell>
                  <ActionsCell>
                    <DropdownMenuItem onClick={() => openEdit(form)}>
                      <Pencil className="h-4 w-4 me-2" />
                      {__('Edit', 'wp-sms')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => copyPopupShortcode(form.slug)}>
                      <ClipboardCopy className="h-4 w-4 me-2" />
                      {__('Copy Popup Shortcode', 'wp-sms')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => copyEmbedShortcode(form.slug)}>
                      <ClipboardCopy className="h-4 w-4 me-2" />
                      {__('Copy Embed Shortcode', 'wp-sms')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => duplicate(form.id)}>
                      <Copy className="h-4 w-4 me-2" />
                      {__('Duplicate', 'wp-sms')}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      onClick={() => handleDelete(form.id)}
                      className="text-destructive focus:text-destructive"
                    >
                      <Trash2 className="h-4 w-4 me-2" />
                      {__('Delete', 'wp-sms')}
                    </DropdownMenuItem>
                  </ActionsCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DataTable>
      </div>

      <Drawer open={panelOpen} onOpenChange={setPanelOpen}>
        <DrawerContent className="sm:max-w-lg overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>{isEdit ? __('Edit Form', 'wp-sms') : __('Create Registration Form', 'wp-sms')}</DrawerTitle>
            <DrawerDescription>
              {isEdit ? __('Update the registration form settings.', 'wp-sms') : __('Configure a new registration form with custom fields and settings.', 'wp-sms')}
            </DrawerDescription>
          </DrawerHeader>

          <div className="space-y-4 px-4">
            <Field>
              <FieldLabel htmlFor="rf-name">{__('Name *', 'wp-sms')}</FieldLabel>
              <Input
                id="rf-name"
                value={formState.name}
                onChange={(e) => {
                  const name = e.target.value;
                  setFormState((prev) => ({
                    ...prev,
                    name,
                    slug: slugManual ? prev.slug : generateSlug(name),
                  }));
                }}
                placeholder={__('e.g. Vendor Registration', 'wp-sms')}
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-slug">{__('Slug', 'wp-sms')}</FieldLabel>
              <Input
                id="rf-slug"
                dir="ltr"
                value={formState.slug}
                onChange={(e) => {
                  setSlugManual(true);
                  setFormState((prev) => ({ ...prev, slug: e.target.value }));
                }}
                placeholder="vendor-registration"
              />
              <FieldDescription>
                {__('Popup:', 'wp-sms')} <code>[wsms_auth id="{formState.slug || '...'}" view="register"]</code>
                <br />
                {__('Embed:', 'wp-sms')} <code>[wsms_auth id="{formState.slug || '...'}" view="register" mode="embed"]</code>
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-desc">{__('Description', 'wp-sms')}</FieldLabel>
              <Textarea
                id="rf-desc"
                value={formState.description}
                onChange={(e) => setFormState((prev) => ({ ...prev, description: e.target.value }))}
                placeholder={__('Optional description', 'wp-sms')}
                className="h-16"
              />
            </Field>

            <Separator />

            {/* Fields */}
            <div>
              <p className="text-sm font-medium mb-1">{__('Fields *', 'wp-sms')}</p>
              <p className="text-xs text-muted-foreground mb-3">{__('Select which fields to include and mark them as required.', 'wp-sms')}</p>
              <div className="rounded-lg border border-border/50 divide-y divide-border/50">
                {SYSTEM_FIELD_OPTIONS.map((sf) => {
                  const included = formState.fields.find((f) => f.id === sf.id);
                  const fieldIdx = formState.fields.findIndex((f) => f.id === sf.id);
                  return (
                    <div key={sf.id} className="flex items-center gap-3 px-4 py-3">
                      <Checkbox
                        checked={!!included}
                        onCheckedChange={() => toggleField(sf.id)}
                      />
                      <span className="flex-1 text-sm">{sf.label}</span>
                      {included && (
                        <div className="flex items-center gap-1">
                          <Button
                            variant="ghost"
                            size="icon-md"
                            disabled={fieldIdx === 0}
                            onClick={() => moveField(sf.id, 'up')}
                          >
                            <ArrowUp className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon-md"
                            disabled={fieldIdx === formState.fields.length - 1}
                            onClick={() => moveField(sf.id, 'down')}
                          >
                            <ArrowDown className="h-3.5 w-3.5" />
                          </Button>
                          <label className="flex items-center gap-1.5 text-xs ms-2 cursor-pointer">
                            <Checkbox
                              checked={included.required}
                              onCheckedChange={() => toggleFieldRequired(sf.id)}
                            />
                            {__('Required', 'wp-sms')}
                          </label>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            <Separator />

            <Field>
              <FieldLabel>{__('User Role', 'wp-sms')}</FieldLabel>
              <Select
                value={formState.user_role || '__default__'}
                onValueChange={(v) => setFormState((prev) => ({ ...prev, user_role: v === '__default__' ? '' : v }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder={__('WordPress default role', 'wp-sms')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__default__">{__('WordPress default role', 'wp-sms')}</SelectItem>
                  {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                      {role.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldDescription>{__('Role assigned to users who register through this form.', 'wp-sms')}</FieldDescription>
            </Field>

            <Separator />

            {/* Verification Overrides */}
            <div>
              <p className="text-sm font-medium mb-1">{__('Verification Overrides', 'wp-sms')}</p>
              <p className="text-xs text-muted-foreground mb-3">
                {__('Override verification settings per form. Only applies to channels that are enabled in global settings.', 'wp-sms')}
              </p>
              <div className="space-y-3">
                {['email', 'phone'].map((channel) => {
                  const override = formState.auth_overrides[channel]?.verify_at_signup;
                  const selectValue = override === true ? 'enable' : override === false ? 'disable' : 'inherit';

                  return (
                    <Field key={channel} orientation="horizontal">
                      <FieldLabel className="capitalize flex-1">{sprintf(__('%s verify at signup', 'wp-sms'), channel)}</FieldLabel>
                      <Select
                        value={selectValue}
                        onValueChange={(v) => {
                          setFormState((prev) => {
                            const overrides = { ...prev.auth_overrides };
                            if (v === 'enable') {
                              overrides[channel] = { ...overrides[channel], verify_at_signup: true };
                            } else if (v === 'disable') {
                              overrides[channel] = { ...overrides[channel], verify_at_signup: false };
                            } else {
                              const channelOverrides = { ...overrides[channel] };
                              delete channelOverrides.verify_at_signup;
                              if (Object.keys(channelOverrides).length === 0) {
                                delete overrides[channel];
                              } else {
                                overrides[channel] = channelOverrides;
                              }
                            }
                            return { ...prev, auth_overrides: overrides };
                          });
                        }}
                      >
                        <SelectTrigger className="w-32">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="inherit">{__('Inherit', 'wp-sms')}</SelectItem>
                          <SelectItem value="enable">{__('Enable', 'wp-sms')}</SelectItem>
                          <SelectItem value="disable">{__('Disable', 'wp-sms')}</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  );
                })}
              </div>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="rf-redirect">{__('Redirect URL', 'wp-sms')}</FieldLabel>
              <Input
                id="rf-redirect"
                dir="ltr"
                value={formState.redirect_url}
                onChange={(e) => setFormState((prev) => ({ ...prev, redirect_url: e.target.value }))}
                placeholder="/welcome or https://..."
              />
              <FieldDescription>{__('Users will be redirected here after successful registration.', 'wp-sms')}</FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-color">{__('Primary Color', 'wp-sms')}</FieldLabel>
              <div className="flex gap-2">
                <Input
                  id="rf-color"
                  type="color"
                  value={formState.branding.primary_color || '#6366f1'}
                  onChange={(e) =>
                    setFormState((prev) => ({
                      ...prev,
                      branding: { ...prev.branding, primary_color: e.target.value },
                    }))
                  }
                  className="w-12 h-9 p-1 cursor-pointer"
                />
                <Input
                  dir="ltr"
                  value={formState.branding.primary_color || ''}
                  onChange={(e) =>
                    setFormState((prev) => ({
                      ...prev,
                      branding: { ...prev.branding, primary_color: e.target.value },
                    }))
                  }
                  placeholder={__('Inherit from global', 'wp-sms')}
                  className="flex-1"
                />
                {formState.branding.primary_color && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                      setFormState((prev) => {
                        const { primary_color, ...rest } = prev.branding;
                        return { ...prev, branding: rest };
                      })
                    }
                  >
                    {__('Clear', 'wp-sms')}
                  </Button>
                )}
              </div>
              <FieldDescription>{__('Override the global branding color for this form.', 'wp-sms')}</FieldDescription>
            </Field>

            <Separator />

            <Field orientation="horizontal">
              <div className="flex-1">
                <FieldLabel>{__('Active', 'wp-sms')}</FieldLabel>
                <FieldDescription>{__('Inactive forms return 404 when accessed.', 'wp-sms')}</FieldDescription>
              </div>
              <Switch
                checked={formState.status === 'active'}
                onCheckedChange={(checked) =>
                  setFormState((prev) => ({ ...prev, status: checked ? 'active' : 'draft' }))
                }
              />
            </Field>
          </div>

          <DrawerFooter>
            <Button onClick={handleSave} disabled={saving || !formState.name || formState.fields.length === 0}>
              {saving ? __('Saving...', 'wp-sms') : isEdit ? __('Update', 'wp-sms') : __('Create', 'wp-sms')}
            </Button>
          </DrawerFooter>
        </DrawerContent>
      </Drawer>
    </>
  );
}
