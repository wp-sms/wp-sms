import { useState, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { MethodCard } from '@/components/method-card';
import { ProfileFieldSheet } from '@/components/profile-field-sheet';
import { UserPlus, Plus, GripVertical, Pencil, Trash2, ArrowUp, ArrowDown, ListChecks } from 'lucide-react';
import { FIELD_TYPES, formatLabel } from '@/lib/constants';
import type { AuthSettings, ProfileFieldDefinition } from '@/lib/api';

interface ProfileFieldsProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const SYSTEM_FIELDS: ProfileFieldDefinition[] = [
  { id: 'email', type: 'text', label: 'Email', source: 'system', meta_key: 'user_email', visibility: 'both', required: true, sort_order: 1 },
  { id: 'password', type: 'text', label: 'Password', source: 'system', meta_key: 'user_pass', visibility: 'registration', required: true, sort_order: 2 },
  { id: 'phone', type: 'text', label: 'Phone Number', source: 'system', meta_key: 'wsms_phone', visibility: 'both', required: false, sort_order: 3 },
  { id: 'first_name', type: 'text', label: 'First Name', source: 'system', meta_key: 'first_name', visibility: 'both', required: false, sort_order: 4 },
  { id: 'last_name', type: 'text', label: 'Last Name', source: 'system', meta_key: 'last_name', visibility: 'both', required: false, sort_order: 5 },
  { id: 'display_name', type: 'text', label: 'Display Name', source: 'system', meta_key: 'display_name', visibility: 'both', required: false, sort_order: 6 },
];

function getSourceBadgeVariant(source: string) {
  switch (source) {
    case 'system': return 'secondary' as const;
    case 'custom': return 'default' as const;
    case 'meta': return 'outline' as const;
    default: return 'secondary' as const;
  }
}

function mergeFields(profileFields: ProfileFieldDefinition[]): ProfileFieldDefinition[] {
  const merged: ProfileFieldDefinition[] = [];
  const seen = new Set<string>();

  // First, add items from profile_fields in order (these include system overrides + custom).
  for (const f of profileFields) {
    const sys = SYSTEM_FIELDS.find((s) => s.id === f.id);
    if (sys) {
      merged.push({ ...sys, ...f, source: 'system' });
    } else {
      merged.push(f);
    }
    seen.add(f.id);
  }

  // Add system fields not in profile_fields.
  for (const sys of SYSTEM_FIELDS) {
    if (!seen.has(sys.id)) {
      merged.push(sys);
    }
  }

  return merged.sort((a, b) => a.sort_order - b.sort_order);
}

export function ProfileFields({ settings, onUpdate }: ProfileFieldsProps) {
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editingField, setEditingField] = useState<ProfileFieldDefinition | null>(null);
  const [sheetMode, setSheetMode] = useState<'create' | 'meta' | 'edit'>('create');

  const allFields = useMemo(() => mergeFields(settings.profile_fields ?? []), [settings.profile_fields]);

  function saveFields(fields: ProfileFieldDefinition[]) {
    onUpdate('profile_fields', fields.map((f, i) => ({ ...f, sort_order: i + 1 })));
  }

  function handleAdd(mode: 'create' | 'meta') {
    setEditingField(null);
    setSheetMode(mode);
    setSheetOpen(true);
  }

  function handleEdit(field: ProfileFieldDefinition) {
    setEditingField(field);
    setSheetMode('edit');
    setSheetOpen(true);
  }

  function handleDelete(id: string) {
    saveFields(allFields.filter((f) => f.id !== id));
  }

  function handleMoveUp(index: number) {
    if (index <= 0) return;
    const next = [...allFields];
    [next[index - 1], next[index]] = [next[index], next[index - 1]];
    saveFields(next);
  }

  function handleMoveDown(index: number) {
    if (index >= allFields.length - 1) return;
    const next = [...allFields];
    [next[index], next[index + 1]] = [next[index + 1], next[index]];
    saveFields(next);
  }

  function handleSaveField(field: ProfileFieldDefinition) {
    if (editingField) {
      // Edit existing.
      saveFields(allFields.map((f) => (f.id === editingField.id ? field : f)));
    } else {
      // Add new.
      saveFields([...allFields, { ...field, sort_order: allFields.length + 1 }]);
    }
    setSheetOpen(false);
  }

  return (
    <div className="space-y-4">
      <MethodCard
        title="Auto-Create Accounts on Login"
        description="When someone logs in with a phone or email that doesn't have an account yet, automatically create one instead of rejecting them"
        icon={UserPlus}
        enabled={settings.auto_create_users}
        onToggle={(checked) => onUpdate('auto_create_users', checked)}
      />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <ListChecks className="h-4 w-4 text-muted-foreground" />
                Profile Fields
              </CardTitle>
              <CardDescription>
                Configure which fields appear on registration and profile forms
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => handleAdd('meta')}>
                Pick Meta Key
              </Button>
              <Button size="sm" onClick={() => handleAdd('create')}>
                <Plus className="mr-1 h-3.5 w-3.5" />
                Add Field
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border/50 divide-y divide-border/50">
            {allFields.map((field, index) => (
                <div key={field.id} className={`flex items-center gap-3 px-4 py-3 ${field.visibility === 'hidden' ? 'opacity-50' : ''}`}>
                  <GripVertical className="h-4 w-4 shrink-0 text-muted-foreground/50" />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">{field.label}</span>
                      <Badge variant={getSourceBadgeVariant(field.source)} className="text-[10px] px-1.5 py-0">
                        {field.source}
                      </Badge>
                      {field.source !== 'system' && (
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                          {FIELD_TYPES.find((t) => t.value === field.type)?.label ?? field.type}
                        </Badge>
                      )}
                      {field.required && (
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-amber-600 border-amber-300">
                          required
                        </Badge>
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {formatLabel(field.visibility)} {field.meta_key !== field.id ? `\u00B7 ${field.meta_key}` : ''}
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleMoveUp(index)} disabled={index === 0}>
                      <ArrowUp className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleMoveDown(index)} disabled={index === allFields.length - 1}>
                      <ArrowDown className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleEdit(field)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    {field.source !== 'system' && (
                      <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={() => handleDelete(field.id)}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </div>
            ))}
          </div>
        </CardContent>
      </Card>

      <ProfileFieldSheet
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        mode={sheetMode}
        field={editingField}
        existingIds={allFields.map((f) => f.id)}
        onSave={handleSaveField}
      />
    </div>
  );
}
