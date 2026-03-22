import { useState, useEffect } from 'react';
import type { Contact } from '@/lib/api';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PhoneInput } from '@/components/ui/phone-input';
import { ContactCustomFields } from './contact-custom-fields';
import { CONTACT_STATUSES } from '@/lib/constants';

interface ContactFormSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact?: Contact | null;
  onSave: (data: Partial<Contact>) => Promise<unknown>;
}

export function ContactFormSheet({ open, onOpenChange, contact, onSave }: ContactFormSheetProps) {
  const isEdit = !!contact;
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    status: 'subscribed',
    custom_fields: {} as Record<string, unknown>,
  });
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
      });
    } else {
      setForm({ first_name: '', last_name: '', email: '', phone: '', status: 'subscribed', custom_fields: {} });
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
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{isEdit ? 'Edit Contact' : 'New Contact'}</SheetTitle>
          <SheetDescription>{isEdit ? 'Update contact details.' : 'Add a new contact.'}</SheetDescription>
        </SheetHeader>

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
          </Field>

          <Field>
            <FieldLabel htmlFor="cf-phone">Phone</FieldLabel>
            <PhoneInput
              key={contact?.id ?? 'new'}
              value={form.phone}
              onChange={(e164: string) => setForm({ ...form, phone: e164 })}
            />
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
            <p className="text-sm font-medium mb-2">Custom fields</p>
            <ContactCustomFields fields={form.custom_fields} onChange={(cf) => setForm({ ...form, custom_fields: cf })} />
          </div>
        </div>

        <SheetFooter>
          <Button onClick={handleSubmit} disabled={saving || (!form.email && !form.phone)}>
            {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
