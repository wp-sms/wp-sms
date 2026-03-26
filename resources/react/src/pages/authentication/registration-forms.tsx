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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Pencil, Copy, Trash2, ClipboardCopy, FileText, ArrowUp, ArrowDown } from 'lucide-react';
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
      title: 'Delete Registration Form',
      description: 'This will permanently delete this registration form. Existing users registered through this form will not be affected.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (ok) {
      await remove(id);
    }
  }

  async function copyShortcode(text: string) {
    await copyToClipboard(text);
    toast.success('Shortcode copied to clipboard');
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
          title="Registration Forms"
          metadata={pluralize(forms.length, 'form')}
          actions={
            <Button onClick={openCreate} size="sm">
              <Plus className="mr-1 h-3.5 w-3.5" />
              Create Form
            </Button>
          }
        />
        <DataTable
          loading={loading}
          isEmpty={forms.length === 0}
          empty={
            <EmptyState
              icon={FileText}
              title="No registration forms yet"
              description="Create your first registration form to collect different information for different user types."
              action={
                <Button onClick={openCreate} size="sm">
                  <Plus className="mr-1 h-3.5 w-3.5" />
                  Create Form
                </Button>
              }
            />
          }
        >
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Slug</TableHead>
                <TableHead>Fields</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Status</TableHead>
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
                  <TableCell>{form.fields.length} fields</TableCell>
                  <TableCell>
                    {form.user_role ? (
                      <Badge variant="outline">{roles.find((r) => r.value === form.user_role)?.label || form.user_role}</Badge>
                    ) : (
                      <span className="text-muted-foreground text-sm">Default</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant={form.status === 'active' ? 'success' : 'secondary'}>
                      {form.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => openEdit(form)}>
                          <Pencil className="h-4 w-4 mr-2" />
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => copyPopupShortcode(form.slug)}>
                          <ClipboardCopy className="h-4 w-4 mr-2" />
                          Copy Popup Shortcode
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => copyEmbedShortcode(form.slug)}>
                          <ClipboardCopy className="h-4 w-4 mr-2" />
                          Copy Embed Shortcode
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => duplicate(form.id)}>
                          <Copy className="h-4 w-4 mr-2" />
                          Duplicate
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                          onClick={() => handleDelete(form.id)}
                          className="text-destructive focus:text-destructive"
                        >
                          <Trash2 className="h-4 w-4 mr-2" />
                          Delete
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DataTable>
      </div>

      <Drawer open={panelOpen} onOpenChange={setPanelOpen}>
        <DrawerContent className="sm:max-w-lg overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>{isEdit ? 'Edit Form' : 'Create Registration Form'}</DrawerTitle>
            <DrawerDescription>
              {isEdit ? 'Update the registration form settings.' : 'Configure a new registration form with custom fields and settings.'}
            </DrawerDescription>
          </DrawerHeader>

          <div className="space-y-4 px-4">
            <Field>
              <FieldLabel htmlFor="rf-name">Name</FieldLabel>
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
                placeholder="e.g. Vendor Registration"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-slug">Slug</FieldLabel>
              <Input
                id="rf-slug"
                value={formState.slug}
                onChange={(e) => {
                  setSlugManual(true);
                  setFormState((prev) => ({ ...prev, slug: e.target.value }));
                }}
                placeholder="vendor-registration"
              />
              <FieldDescription>
                Popup: <code>[wsms_auth id="{formState.slug || '...'}" view="register"]</code>
                <br />
                Embed: <code>[wsms_auth id="{formState.slug || '...'}" view="register" mode="embed"]</code>
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-desc">Description</FieldLabel>
              <Textarea
                id="rf-desc"
                value={formState.description}
                onChange={(e) => setFormState((prev) => ({ ...prev, description: e.target.value }))}
                placeholder="Optional description"
                className="h-16"
              />
            </Field>

            <Separator />

            {/* Fields */}
            <div>
              <p className="text-sm font-medium mb-1">Fields</p>
              <p className="text-xs text-muted-foreground mb-3">Select which fields to include and mark them as required.</p>
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
                            size="icon"
                            className="h-7 w-7"
                            disabled={fieldIdx === 0}
                            onClick={() => moveField(sf.id, 'up')}
                          >
                            <ArrowUp className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7"
                            disabled={fieldIdx === formState.fields.length - 1}
                            onClick={() => moveField(sf.id, 'down')}
                          >
                            <ArrowDown className="h-3.5 w-3.5" />
                          </Button>
                          <label className="flex items-center gap-1.5 text-xs ml-2 cursor-pointer">
                            <Checkbox
                              checked={included.required}
                              onCheckedChange={() => toggleFieldRequired(sf.id)}
                            />
                            Required
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
              <FieldLabel>User Role</FieldLabel>
              <Select
                value={formState.user_role || '__default__'}
                onValueChange={(v) => setFormState((prev) => ({ ...prev, user_role: v === '__default__' ? '' : v }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="WordPress default role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__default__">WordPress default role</SelectItem>
                  {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                      {role.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldDescription>Role assigned to users who register through this form.</FieldDescription>
            </Field>

            <Separator />

            {/* Verification Overrides */}
            <div>
              <p className="text-sm font-medium mb-1">Verification Overrides</p>
              <p className="text-xs text-muted-foreground mb-3">
                Override verification settings per form. Only applies to channels that are enabled in global settings.
              </p>
              <div className="space-y-3">
                {['email', 'phone'].map((channel) => {
                  const override = formState.auth_overrides[channel]?.verify_at_signup;
                  const selectValue = override === true ? 'enable' : override === false ? 'disable' : 'inherit';

                  return (
                    <Field key={channel} orientation="horizontal">
                      <FieldLabel className="capitalize flex-1">{channel} verify at signup</FieldLabel>
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
                          <SelectItem value="inherit">Inherit</SelectItem>
                          <SelectItem value="enable">Enable</SelectItem>
                          <SelectItem value="disable">Disable</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  );
                })}
              </div>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="rf-redirect">Redirect URL</FieldLabel>
              <Input
                id="rf-redirect"
                value={formState.redirect_url}
                onChange={(e) => setFormState((prev) => ({ ...prev, redirect_url: e.target.value }))}
                placeholder="/welcome or https://..."
              />
              <FieldDescription>Users will be redirected here after successful registration.</FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="rf-color">Primary Color</FieldLabel>
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
                  value={formState.branding.primary_color || ''}
                  onChange={(e) =>
                    setFormState((prev) => ({
                      ...prev,
                      branding: { ...prev.branding, primary_color: e.target.value },
                    }))
                  }
                  placeholder="Inherit from global"
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
                    Clear
                  </Button>
                )}
              </div>
              <FieldDescription>Override the global branding color for this form.</FieldDescription>
            </Field>

            <Separator />

            <Field orientation="horizontal">
              <div className="flex-1">
                <FieldLabel>Active</FieldLabel>
                <FieldDescription>Inactive forms return 404 when accessed.</FieldDescription>
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
              {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
            </Button>
          </DrawerFooter>
        </DrawerContent>
      </Drawer>
    </>
  );
}
