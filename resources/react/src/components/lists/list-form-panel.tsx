import { __ } from '@wordpress/i18n';
import { useState, useEffect } from 'react';
import type { ContactList, SegmentConditionGroup, Tag } from '@/lib/api';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription, DrawerFooter } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SegmentBuilder } from './segment-builder';

interface ListFormPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  list?: ContactList | null;
  tags: Tag[];
  onSave: (data: Partial<ContactList>) => Promise<unknown>;
}

const DEFAULT_CONDITIONS: SegmentConditionGroup = {
  match: 'all',
  conditions: [{ type: 'attribute', field: 'status', operator: 'equals', value: 'subscribed' }],
};

export function ListFormPanel({ open, onOpenChange, list, tags, onSave }: ListFormPanelProps) {
  const isEdit = !!list;
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [type, setType] = useState<'static' | 'dynamic'>('dynamic');
  const [tagId, setTagId] = useState('');
  const [conditions, setConditions] = useState<SegmentConditionGroup>(DEFAULT_CONDITIONS);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (list) {
      setName(list.name);
      setDescription(list.description ?? '');
      setType(list.type);
      setTagId(list.tag_id ?? '');
      setConditions(list.conditions ?? DEFAULT_CONDITIONS);
    } else {
      setName('');
      setDescription('');
      setType('dynamic');
      setTagId('');
      setConditions(DEFAULT_CONDITIONS);
    }
  }, [list, open]);

  const handleSubmit = async () => {
    setSaving(true);
    try {
      await onSave({
        name,
        type,
        description: description || null,
        tag_id: type === 'static' ? tagId : null,
        conditions: type === 'dynamic' ? conditions : null,
      });
      onOpenChange(false);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="sm:max-w-lg overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle>{isEdit ? 'Edit List' : 'New List'}</DrawerTitle>
          <DrawerDescription>{isEdit ? 'Update list configuration.' : 'Create a new contact list.'}</DrawerDescription>
        </DrawerHeader>

        <div className="space-y-4 px-4">
          <Field>
            <FieldLabel htmlFor="list-name">Name *</FieldLabel>
            <Input id="list-name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>

          <Field>
            <FieldLabel htmlFor="list-desc">Description</FieldLabel>
            <Textarea id="list-desc" value={description} onChange={(e) => setDescription(e.target.value)} className="h-16" />
          </Field>

          <Field>
            <FieldLabel>{__('Type', 'wp-sms')}</FieldLabel>
            <Select value={type} onValueChange={(v) => setType(v as 'static' | 'dynamic')}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="dynamic">{__('Dynamic (conditions)', 'wp-sms')}</SelectItem>
                <SelectItem value="static">{__('Static (tag-based)', 'wp-sms')}</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          {type === 'static' && (
            <Field>
              <FieldLabel>{__('Tag', 'wp-sms')}</FieldLabel>
              <Select value={tagId} onValueChange={setTagId}>
                <SelectTrigger>
                  <SelectValue placeholder={__('Select a tag...', 'wp-sms')} />
                </SelectTrigger>
                <SelectContent>
                  {tags.map((tag) => (
                    <SelectItem key={tag.id} value={tag.id}>
                      <span className="flex items-center gap-2">
                        <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                        {tag.name}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}

          {type === 'dynamic' && (
            <div>
              <p className="text-sm font-medium mb-2">{__('Conditions', 'wp-sms')}</p>
              <SegmentBuilder conditions={conditions} tags={tags} onChange={setConditions} />
            </div>
          )}
        </div>

        <DrawerFooter>
          <Button onClick={handleSubmit} disabled={saving || !name.trim()}>
            {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
