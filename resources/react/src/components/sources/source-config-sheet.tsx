import { useState, useEffect } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldMappingTable } from './field-mapping-table';
import { getConfig, type ContactSource, type ContactSourceConfig, type ContactSourceFields } from '@/lib/api';

interface SourceConfigSheetProps {
  source: ContactSource | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSave: (type: string, config: ContactSourceConfig) => Promise<void>;
  getFields: (type: string) => Promise<ContactSourceFields>;
}

export function SourceConfigSheet({ source, open, onOpenChange, onSave, getFields }: SourceConfigSheetProps) {
  const [fields, setFields] = useState<ContactSourceFields | null>(null);
  const [roles, setRoles] = useState<string[]>([]);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [autoSync, setAutoSync] = useState(true);
  const [saving, setSaving] = useState(false);

  const allRoles = getConfig().roles;

  useEffect(() => {
    if (!source || !open) return;

    let cancelled = false;

    // Populate from existing config
    const hasExistingMapping = source.config && Object.keys(source.config.field_mapping ?? {}).length > 0;
    if (source.config) {
      setRoles(source.config.roles ?? []);
      setMapping(source.config.field_mapping ?? {});
      setAutoSync(source.config.auto_sync ?? true);
    } else {
      setRoles([]);
      setMapping({});
      setAutoSync(true);
    }

    // Load available fields; apply defaults if no existing mapping
    getFields(source.type).then((result) => {
      if (cancelled) return;
      setFields(result);
      if (!hasExistingMapping) {
        setMapping(result.default_mapping);
      }
    }).catch(() => {});

    return () => { cancelled = true; };
  }, [source, open, getFields]);

  const handleSave = async () => {
    if (!source) return;
    setSaving(true);
    try {
      await onSave(source.type, {
        roles,
        field_mapping: mapping,
        auto_sync: autoSync,
      });
      onOpenChange(false);
    } finally {
      setSaving(false);
    }
  };

  const toggleRole = (role: string) => {
    setRoles((prev) =>
      prev.includes(role) ? prev.filter((r) => r !== role) : [...prev, role],
    );
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{source?.name ?? 'Configure Source'}</SheetTitle>
          <SheetDescription>
            Configure which users to sync and how fields are mapped.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-6 px-4">
          {/* Role Selection */}
          <div className="space-y-3">
            <Label>User Roles</Label>
            <p className="text-xs text-muted-foreground">
              Only sync users with these roles. Leave all unchecked to sync all roles.
            </p>
            <div className="space-y-2">
              {Object.entries(allRoles).map(([slug, label]) => (
                <label key={slug} className="flex items-center gap-2 text-sm cursor-pointer">
                  <Checkbox
                    checked={roles.includes(slug)}
                    onCheckedChange={() => toggleRole(slug)}
                  />
                  {label}
                </label>
              ))}
            </div>
          </div>

          {/* Field Mapping */}
          <div className="space-y-3">
            <Label>Field Mapping</Label>
            <p className="text-xs text-muted-foreground">
              Map WordPress user fields to contact fields.
            </p>
            {fields ? (
              <FieldMappingTable
                mapping={mapping}
                availableFields={fields.fields}
                onChange={setMapping}
              />
            ) : (
              <p className="text-sm text-muted-foreground">Loading fields...</p>
            )}
          </div>

          {/* Auto Sync */}
          <div className="flex items-center justify-between">
            <div>
              <Label>Auto-sync</Label>
              <p className="text-xs text-muted-foreground">
                Sync on user registration, profile updates, and deletion.
              </p>
            </div>
            <Switch checked={autoSync} onCheckedChange={setAutoSync} />
          </div>
        </div>

        <SheetFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? 'Saving...' : source?.status === 'disconnected' ? 'Connect & Save' : 'Save Changes'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
