import { useState, useEffect } from 'react';
import type { Contact } from '@/lib/api';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription, DrawerFooter } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PhoneInput } from '@/components/ui/phone-input';
import { Checkbox } from '@/components/ui/checkbox';
import { ContactCustomFields } from './contact-custom-fields';
import { CONTACT_STATUSES, OPT_OUT_CHANNELS } from '@/lib/constants';

interface ContactFormPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact?: Contact | null;
  onSave: (data: Partial<Contact>) => Promise<unknown>;
}

const EMPTY_FORM = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  status: 'subscribed',
  custom_fields: {} as Record<string, unknown>,
  channel_opt_outs: {} as Record<string, string | null>,
  email_verified: false,
  phone_verified: false,
};

export function ContactFormPanel({ open, onOpenChange, contact, onSave }: ContactFormPanelProps) {
  const isEdit = !!contact;
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (contact) {
      setForm({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        email: contact.email || '',
        phone: contact.phone || '',
        status: contact.status || 'subscribed',
        custom_fields: contact.custom_fields || {},
        channel_opt_outs: contact.channel_opt_outs || {},
        email_verified: !!contact.email_verified,
        phone_verified: !!contact.phone_verified,
      });
    } else {
      setForm({ ...EMPTY_FORM });
    }
  }, [contact, open]);

  const handleSubmit = async () => {
    setSaving(true);
    try {
      await onSave(form);
      onOpenChange(false);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle>{isEdit ? 'Edit Contact' : 'New Contact'}</DrawerTitle>
          <DrawerDescription>{isEdit ? 'Update contact details.' : 'Add a new contact.'}</DrawerDescription>
        </DrawerHeader>

        <div className="space-y-4 px-4">
          <div className="grid grid-cols-2 gap-3">
            <Field>
              <FieldLabel htmlFor="cf-first">First name</FieldLabel>
              <Input id="cf-first" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
            </Field>
            <Field>
              <FieldLabel htmlFor="cf-last">Last name</FieldLabel>
              <Input id="cf-last" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
            </Field>
          </div>

          <Field>
            <FieldLabel htmlFor="cf-email">Email</FieldLabel>
            <Input id="cf-email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            {form.email && (
              <label className="flex items-center gap-2 text-sm text-muted-foreground mt-1">
                <Checkbox checked={form.email_verified} onCheckedChange={(v) => setForm({ ...form, email_verified: !!v })} />
                Email verified
              </label>
            )}
          </Field>

          <Field>
            <FieldLabel htmlFor="cf-phone">Phone</FieldLabel>
            <PhoneInput
              key={contact?.id ?? 'new'}
              value={form.phone}
              onChange={(e164: string) => setForm({ ...form, phone: e164 })}
            />
            {form.phone && (
              <label className="flex items-center gap-2 text-sm text-muted-foreground mt-1">
                <Checkbox checked={form.phone_verified} onCheckedChange={(v) => setForm({ ...form, phone_verified: !!v })} />
                Phone verified
              </label>
            )}
          </Field>

          <Field>
            <FieldLabel htmlFor="cf-status">Status</FieldLabel>
            <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
              <SelectTrigger id="cf-status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CONTACT_STATUSES.map((s) => (
                  <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div>
            <p className="text-sm font-medium mb-2">Channel opt-outs</p>
            <p className="text-xs text-muted-foreground mb-2">
              Opted-out channels won&apos;t receive campaign messages.
            </p>
            {OPT_OUT_CHANNELS.map(({ value: ch, label }) => (
              <label key={ch} className="flex items-center gap-2 text-sm text-muted-foreground mb-1.5">
                <Checkbox
                  checked={form.channel_opt_outs[ch] != null}
                  onCheckedChange={(checked) => {
                    const next = { ...form.channel_opt_outs };
                    next[ch] = checked ? new Date().toISOString() : null;
                    setForm({ ...form, channel_opt_outs: next });
                  }}
                />
                Opted out of {label}
              </label>
            ))}
          </div>

          <div>
            <p className="text-sm font-medium mb-2">Custom fields</p>
            <ContactCustomFields fields={form.custom_fields} onChange={(cf) => setForm({ ...form, custom_fields: cf })} />
          </div>
        </div>

        <DrawerFooter>
          <Button onClick={handleSubmit} disabled={saving || (!form.email && !form.phone)}>
            {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
