import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
} from '@/components/ui/sheet';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Pencil, Copy, Trash2, ClipboardCopy, ClipboardList } from 'lucide-react';
import { useConfirm } from '@/components/confirm-provider';
import { useSubscriptionForms, type SubscriptionFormData } from '@/hooks/use-subscription-forms';
import { useLists } from '@/hooks/use-lists';
import { copyToClipboard, generateSlug } from '@/lib/utils';
import { toast } from 'sonner';
import type { SubscriptionFormField } from '@/lib/api';

const AVAILABLE_FIELDS = [
  { key: 'email', label: 'Email Address' },
  { key: 'phone', label: 'Phone Number' },
  { key: 'first_name', label: 'First Name' },
  { key: 'last_name', label: 'Last Name' },
];

interface FormEditorState {
  name: string;
  slug: string;
  status: string;
  fields: SubscriptionFormField[];
  list_id: string | null;
  tag_id: string | null;
  double_optin: boolean;
  optin_channel: string;
  appearance: Record<string, string>;
  success_message: string;
  redirect_url: string;
}

const EMPTY_FORM: FormEditorState = {
  name: '',
  slug: '',
  status: 'active',
  fields: [{ key: 'email', required: true, label: 'Email Address' }],
  list_id: null,
  tag_id: null,
  double_optin: false,
  optin_channel: 'email',
  appearance: { button_text: 'Subscribe' },
  success_message: 'Thanks for subscribing!',
  redirect_url: '',
};

export function SubscriptionForms() {
  const { forms, loading, create, update, remove, duplicate } = useSubscriptionForms();
  const { lists } = useLists('static');
  const confirm = useConfirm();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editingForm, setEditingForm] = useState<SubscriptionFormData | null>(null);
  const [formState, setFormState] = useState<FormEditorState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [slugManual, setSlugManual] = useState(false);

  const isEdit = !!editingForm;

  useEffect(() => {
    if (editingForm) {
      setFormState({
        name: editingForm.name,
        slug: editingForm.slug,
        status: editingForm.status,
        fields: editingForm.fields,
        list_id: editingForm.list_id,
        tag_id: editingForm.tag_id,
        double_optin: editingForm.double_optin,
        optin_channel: editingForm.optin_channel,
        appearance: editingForm.appearance,
        success_message: editingForm.success_message,
        redirect_url: editingForm.redirect_url || '',
      });
      setSlugManual(true);
    } else {
      setFormState(EMPTY_FORM);
      setSlugManual(false);
    }
  }, [editingForm, sheetOpen]);

  function openCreate() {
    setEditingForm(null);
    setSheetOpen(true);
  }

  function openEdit(form: SubscriptionFormData) {
    setEditingForm(form);
    setSheetOpen(true);
  }

  async function handleSave() {
    setSaving(true);
    try {
      const payload = {
        ...formState,
        redirect_url: formState.redirect_url || null,
      };
      if (editingForm) {
        await update(editingForm.id, payload);
      } else {
        await create(payload);
      }
      setSheetOpen(false);
    } catch (err: any) {
      toast.error(err.message || 'Failed to save form');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    const ok = await confirm({
      title: 'Delete Subscription Form',
      description: 'This will permanently delete this subscription form. Existing contacts collected through this form will not be affected.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (ok) {
      await remove(id);
    }
  }

  async function copyShortcode(slug: string) {
    await copyToClipboard(`[wsms_subscribe id="${slug}"]`);
    toast.success('Shortcode copied to clipboard');
  }

  function toggleField(key: string) {
    setFormState((prev) => {
      const exists = prev.fields.find((f) => f.key === key);
      if (exists) {
        return { ...prev, fields: prev.fields.filter((f) => f.key !== key) };
      }
      const def = AVAILABLE_FIELDS.find((f) => f.key === key);
      return { ...prev, fields: [...prev.fields, { key, required: false, label: def?.label || key }] };
    });
  }

  function toggleFieldRequired(key: string) {
    setFormState((prev) => ({
      ...prev,
      fields: prev.fields.map((f) => f.key === key ? { ...f, required: !f.required } : f),
    }));
  }

  function updateFieldLabel(key: string, label: string) {
    setFormState((prev) => ({
      ...prev,
      fields: prev.fields.map((f) => f.key === key ? { ...f, label } : f),
    }));
  }

  const enabledFieldKeys = formState.fields.map((f) => f.key);
  const optinChannelOptions = enabledFieldKeys.filter((k) => k === 'email' || k === 'phone');

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Subscription Forms</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground">Loading...</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle className="flex items-center gap-2 text-base">
              <ClipboardList className="h-4 w-4 text-muted-foreground" />
              Subscription Forms
            </CardTitle>
            <CardDescription>
              {forms.length} {forms.length === 1 ? 'form' : 'forms'} total
            </CardDescription>
          </div>
          <Button onClick={openCreate} size="sm">
            <Plus className="mr-1 h-3.5 w-3.5" />
            Create Form
          </Button>
        </CardHeader>
        <CardContent>
          {forms.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <ClipboardList className="h-12 w-12 text-muted-foreground/50 mb-4" />
              <h3 className="text-lg font-medium mb-1">No subscription forms yet</h3>
              <p className="text-muted-foreground text-sm mb-4">
                Create your first subscription form to collect contacts from visitors without requiring WordPress registration.
              </p>
              <Button onClick={openCreate} size="sm">
                <Plus className="mr-1 h-3.5 w-3.5" />
                Create Form
              </Button>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Fields</TableHead>
                  <TableHead>Double Opt-in</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-[70px]" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {forms.map((form) => (
                  <TableRow key={form.id}>
                    <TableCell className="font-medium">{form.name}</TableCell>
                    <TableCell>
                      <code className="text-xs bg-muted px-1.5 py-0.5 rounded">{form.slug}</code>
                    </TableCell>
                    <TableCell>{form.fields.length} fields</TableCell>
                    <TableCell>
                      {form.double_optin ? (
                        <Badge variant="outline">{form.optin_channel}</Badge>
                      ) : (
                        <span className="text-muted-foreground text-sm">Off</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge variant={form.status === 'active' ? 'default' : 'secondary'}>
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
                          <DropdownMenuItem onClick={() => copyShortcode(form.slug)}>
                            <ClipboardCopy className="h-4 w-4 mr-2" />
                            Copy Shortcode
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
          )}
        </CardContent>
      </Card>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className="sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle>{isEdit ? 'Edit Form' : 'Create Subscription Form'}</SheetTitle>
            <SheetDescription>
              {isEdit ? 'Update the subscription form settings.' : 'Configure a new form to collect subscriber contacts.'}
            </SheetDescription>
          </SheetHeader>

          <div className="space-y-4 px-4">
            <Field>
              <FieldLabel htmlFor="sf-name">Name</FieldLabel>
              <Input
                id="sf-name"
                value={formState.name}
                onChange={(e) => {
                  const name = e.target.value;
                  setFormState((prev) => ({
                    ...prev,
                    name,
                    slug: slugManual ? prev.slug : generateSlug(name),
                  }));
                }}
                placeholder="e.g. Newsletter Signup"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="sf-slug">Slug</FieldLabel>
              <Input
                id="sf-slug"
                value={formState.slug}
                onChange={(e) => {
                  setSlugManual(true);
                  setFormState((prev) => ({ ...prev, slug: e.target.value }));
                }}
                placeholder="newsletter"
              />
              <FieldDescription>
                Shortcode: <code>[wsms_subscribe id="{formState.slug || '...'}"]</code>
              </FieldDescription>
            </Field>

            <Separator />

            {/* Fields */}
            <div>
              <p className="text-sm font-medium mb-1">Fields</p>
              <p className="text-xs text-muted-foreground mb-3">Select which fields to include and mark them as required.</p>
              <div className="rounded-lg border border-border/50 divide-y divide-border/50">
                {AVAILABLE_FIELDS.map((af) => {
                  const included = formState.fields.find((f) => f.key === af.key);
                  return (
                    <div key={af.key} className="flex items-center gap-3 px-4 py-3">
                      <Checkbox
                        checked={!!included}
                        onCheckedChange={() => toggleField(af.key)}
                      />
                      <div className="flex-1">
                        {included ? (
                          <Input
                            value={included.label}
                            onChange={(e) => updateFieldLabel(af.key, e.target.value)}
                            className="h-8 text-sm"
                          />
                        ) : (
                          <span className="text-sm">{af.label}</span>
                        )}
                      </div>
                      {included && (
                        <label className="flex items-center gap-1.5 text-xs ml-2 cursor-pointer">
                          <Checkbox
                            checked={included.required}
                            onCheckedChange={() => toggleFieldRequired(af.key)}
                          />
                          Required
                        </label>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            <Separator />

            <Field>
              <FieldLabel>Subscribe to List</FieldLabel>
              <Select
                value={formState.list_id || '__none__'}
                onValueChange={(v) => setFormState((prev) => ({ ...prev, list_id: v === '__none__' ? null : v }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="No list" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">No list</SelectItem>
                  {lists.map((list) => (
                    <SelectItem key={list.id} value={list.id}>{list.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldDescription>Only static lists are shown — dynamic lists are computed automatically.</FieldDescription>
            </Field>

            <Separator />

            {/* Double Opt-in */}
            <div>
              <p className="text-sm font-medium mb-1">Double Opt-in</p>
              <p className="text-xs text-muted-foreground mb-3">
                Send a verification code before confirming the subscription.
              </p>
              <div className="space-y-3">
                <Field orientation="horizontal">
                  <FieldLabel className="flex-1">Require verification</FieldLabel>
                  <Switch
                    checked={formState.double_optin}
                    onCheckedChange={(checked) => setFormState((prev) => ({ ...prev, double_optin: checked }))}
                  />
                </Field>
                {formState.double_optin && optinChannelOptions.length > 0 && (
                  <Field>
                    <FieldLabel>Verification channel</FieldLabel>
                    <Select
                      value={formState.optin_channel}
                      onValueChange={(v) => setFormState((prev) => ({ ...prev, optin_channel: v }))}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {optinChannelOptions.map((ch) => (
                          <SelectItem key={ch} value={ch}>{ch === 'email' ? 'Email' : 'Phone (SMS)'}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                )}
              </div>
            </div>

            <Separator />

            <Field>
              <FieldLabel htmlFor="sf-btn-text">Button Text</FieldLabel>
              <Input
                id="sf-btn-text"
                value={formState.appearance.button_text || ''}
                onChange={(e) => setFormState((prev) => ({ ...prev, appearance: { ...prev.appearance, button_text: e.target.value } }))}
                placeholder="Subscribe"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="sf-success-msg">Success Message</FieldLabel>
              <Input
                id="sf-success-msg"
                value={formState.success_message}
                onChange={(e) => setFormState((prev) => ({ ...prev, success_message: e.target.value }))}
                placeholder="Thanks for subscribing!"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="sf-redirect">Redirect URL</FieldLabel>
              <Input
                id="sf-redirect"
                value={formState.redirect_url}
                onChange={(e) => setFormState((prev) => ({ ...prev, redirect_url: e.target.value }))}
                placeholder="/thank-you or https://..."
              />
              <FieldDescription>Users will be redirected here after subscribing. Leave empty to show the success message.</FieldDescription>
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

          <SheetFooter>
            <Button onClick={handleSave} disabled={saving || !formState.name || formState.fields.length === 0}>
              {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </>
  );
}
