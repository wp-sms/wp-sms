import { useCallback, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Shield, Check, Eye, Minus, Info } from 'lucide-react';
import { api } from '@/lib/api';
import { NAV_ITEMS, SECTION_IDS } from '@/components/layout/app-shell';
import { PageHeader } from '@/components/layout/page-header';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';

interface ProfileOption {
  id: string;
  label: string;
}

interface AccessData {
  profiles: Record<string, string>;
  available_profiles: ProfileOption[];
  roles: Record<string, string>;
  sections: Record<string, { view: string | null; manage: string | null }>;
  profile_caps: Record<string, string[]>;
}

const SECTION_LABELS: Record<string, string> = Object.fromEntries(
  NAV_ITEMS.map((item) => [item.id, item.label]),
);

function getCapLevel(profileCaps: string[], section: { view: string | null; manage: string | null }): 'manage' | 'view' | 'none' {
  if (section.manage && profileCaps.includes(section.manage)) return 'manage';
  if (section.view && profileCaps.includes(section.view)) return 'view';
  return 'none';
}

function CapIcon({ level }: { level: 'manage' | 'view' | 'none' }) {
  switch (level) {
    case 'manage':
      return <Check className="size-4 text-muted-foreground" />;
    case 'view':
      return <Eye className="size-4 text-muted-foreground" />;
    case 'none':
      return <Minus className="size-4 text-muted-foreground/40" />;
  }
}

export function AccessControlPage() {
  const [data, setData] = useState<AccessData | null>(null);
  const [profiles, setProfiles] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    api.get<{ success: boolean; data: AccessData }>('access/profiles')
      .then((res) => {
        setData(res.data);
        setProfiles(res.data.profiles);
      })
      .finally(() => setLoading(false));
  }, []);

  const handleProfileChange = useCallback((role: string, profileId: string) => {
    setProfiles((prev) => ({ ...prev, [role]: profileId }));
    setDirty(true);
  }, []);

  const handleSave = useCallback(async () => {
    setSaving(true);
    try {
      await api.put('access/profiles', { profiles });
      setDirty(false);
      toast.success(__('Access profiles saved.', 'wp-sms'));
    } catch {
      toast.error(__('Failed to save access profiles.', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  }, [profiles]);

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!data) return null;

  const selectableProfiles = data.available_profiles;

  return (
    <div className="space-y-6">
      <PageHeader icon={Shield} title={__('Access Control', 'wp-sms')} />

      <div className="flex items-start gap-2 rounded-md border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
        <Info className="size-4 mt-0.5 shrink-0" />
        <p>{__('Viewer and above can see contact data. Operator and above can modify and export it. Only grant access to roles that handle customer data.', 'wp-sms')}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{__('Role Access Profiles', 'wp-sms')}</CardTitle>
          <CardDescription>
            {__('Assign an access level to each WordPress role. Administrators always have full access.', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {Object.entries(data.roles).map(([slug, name]) => {
              const currentProfile = profiles[slug] ?? 'no_access';
              const isCustom = currentProfile === 'custom';

              return (
                <div key={slug} className="flex items-center justify-between rounded-lg border px-4 py-3">
                  <div className="flex items-center gap-3">
                    <span className="text-sm font-medium">{name}</span>
                    <Badge variant="outline" className="text-xs font-normal text-muted-foreground">{slug}</Badge>
                  </div>
                  {isCustom ? (
                    <Badge variant="secondary">{__('Custom', 'wp-sms')}</Badge>
                  ) : (
                    <Select value={currentProfile} onValueChange={(val) => handleProfileChange(slug, val)}>
                      <SelectTrigger className="w-40">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {selectableProfiles.map((p) => (
                          <SelectItem key={p.id} value={p.id}>{p.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </div>
              );
            })}
          </div>

          <div className="mt-4 flex items-center justify-between">
            <p className="text-xs text-muted-foreground">
              {__('Need finer control? Use a WordPress role editor plugin to toggle individual capabilities.', 'wp-sms')}
            </p>
            <Button onClick={handleSave} disabled={!dirty || saving}>
              {saving ? __('Saving…', 'wp-sms') : __('Save Changes', 'wp-sms')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{__('Profile Comparison', 'wp-sms')}</CardTitle>
          <CardDescription>
            {__('What each access level includes across plugin sections.', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="py-2 pe-4 text-start font-medium text-muted-foreground">{__('Section', 'wp-sms')}</th>
                  {selectableProfiles.filter((p) => p.id !== 'no_access').map((p) => (
                    <th key={p.id} className="px-3 py-2 text-center font-medium text-muted-foreground">{p.label}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {SECTION_IDS.map((sectionKey) => (
                  <tr key={sectionKey} className="border-b last:border-0">
                    <td className="py-2.5 pe-4 font-medium">{SECTION_LABELS[sectionKey]}</td>
                    {selectableProfiles.filter((p) => p.id !== 'no_access').map((p) => {
                      const profileCaps = data.profile_caps[p.id] ?? [];
                      const sectionCaps = data.sections[sectionKey];
                      const level = getCapLevel(profileCaps, sectionCaps);
                      return (
                        <td key={p.id} className="px-3 py-2.5 text-center">
                          <div className="flex items-center justify-center" title={level}>
                            <CapIcon level={level} />
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="mt-4 flex items-center gap-6 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5"><Check className="size-3.5 text-muted-foreground" /> {__('Full access', 'wp-sms')}</span>
            <span className="flex items-center gap-1.5"><Eye className="size-3.5 text-muted-foreground" /> {__('View only', 'wp-sms')}</span>
            <span className="flex items-center gap-1.5"><Minus className="size-3.5 text-muted-foreground/40" /> {__('No access', 'wp-sms')}</span>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
