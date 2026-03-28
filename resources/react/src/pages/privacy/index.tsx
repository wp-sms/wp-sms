import { __, sprintf } from '@wordpress/i18n';
import { useState } from 'react';
import { PageHeader } from '@/components/layout/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useConfirm } from '@/components/confirm-provider';
import { api, getConfig } from '@/lib/api';
import { toast } from 'sonner';
import { Shield, Search, Download, Trash2, User, Mail, Phone, Tag, Loader2, ExternalLink, Info } from 'lucide-react';

interface ContactTag {
  id: string;
  name: string;
  color: string;
}

interface LookupContact {
  id: string;
  email: string | null;
  phone: string | null;
  first_name: string | null;
  last_name: string | null;
  status: string;
  source: string | null;
  tags: ContactTag[];
  created_at: string;
}

interface LookupResult {
  success: boolean;
  data: {
    contacts: LookupContact[];
    wp_user: { id: number; display_name: string; email: string } | null;
    message_log_count: number;
    auth_log_count: number;
  };
}

interface ExportResult {
  success: boolean;
  data: { url: string; filename: string };
}

interface EraseResult {
  success: boolean;
  data: {
    contacts_removed: number;
    message_logs_anonymized: number;
    auth_logs_removed: number;
  };
}

export function PrivacyPage() {
  const [identifier, setIdentifier] = useState('');
  const [lookup, setLookup] = useState<LookupResult['data'] | null>(null);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [erasing, setErasing] = useState(false);
  const confirm = useConfirm();

  const hasData = lookup && (lookup.contacts.length > 0 || lookup.wp_user);
  const { adminUrl } = getConfig();

  async function handleLookup(e: React.FormEvent) {
    e.preventDefault();
    if (!identifier.trim()) return;

    setLoading(true);
    setLookup(null);
    try {
      const res = await api.post<LookupResult>('wsms/v1/privacy/lookup', { identifier: identifier.trim() });
      setLookup(res.data);
    } catch {
      toast.error(__('Failed to look up identifier.', 'wp-sms'));
    } finally {
      setLoading(false);
    }
  }

  async function handleExport() {
    setExporting(true);
    try {
      const res = await api.post<ExportResult>('wsms/v1/privacy/export', { identifier: identifier.trim() });
      window.open(res.data.url, '_blank');
      toast.success(sprintf(__('Export ready: %s', 'wp-sms'), res.data.filename));
    } catch {
      toast.error(__('Failed to export data.', 'wp-sms'));
    } finally {
      setExporting(false);
    }
  }

  async function handleErase() {
    const ok = await confirm({
      title: 'Erase all data?',
      description: `This will permanently delete all contacts, anonymize message logs, and remove auth logs for "${identifier}". This action cannot be undone.`,
      confirmLabel: 'Erase Data',
      variant: 'destructive',
    });
    if (!ok) return;

    setErasing(true);
    try {
      const res = await api.post<EraseResult>('wsms/v1/privacy/erase', { identifier: identifier.trim() });
      const { contacts_removed, message_logs_anonymized, auth_logs_removed } = res.data;
      toast.success(
        `Erased: ${contacts_removed} contact(s), ${message_logs_anonymized} message log(s) anonymized, ${auth_logs_removed} auth log(s) removed.`,
      );
      setLookup(null);
      setIdentifier('');
    } catch {
      toast.error(__('Failed to erase data.', 'wp-sms'));
    } finally {
      setErasing(false);
    }
  }

  return (
    <>
      <PageHeader icon={Shield} title={__('Privacy', 'wp-sms')} />

      <div className="space-y-6 max-w-2xl">
        <p className="text-sm text-muted-foreground">
          Look up, export, or erase personal data stored by WSMS. Unlike WordPress's built-in privacy tools which only support email, this page also supports phone number lookups — essential for phone-only contacts.
        </p>

        <Card>
          <CardHeader>
            <CardTitle>{__('Look Up Personal Data', 'wp-sms')}</CardTitle>
            <CardDescription>
              Enter an email address or phone number to find all associated WSMS data including contact profiles, tags, message history, and authentication logs. Phone-only contacts can only be found here — they won't appear in WordPress's built-in privacy tools.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleLookup} className="flex items-end gap-3">
              <div className="flex-1 space-y-1.5">
                <Label htmlFor="identifier">{__('Email or Phone', 'wp-sms')}</Label>
                <Input
                  id="identifier"
                  placeholder={__('user@example.com or +1234567890', 'wp-sms')}
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                />
              </div>
              <Button type="submit" disabled={loading || !identifier.trim()}>
                {loading ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}
                Look Up
              </Button>
            </form>
          </CardContent>
        </Card>

        <div className="flex items-start gap-2 rounded-md border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
          <Info className="size-4 mt-0.5 shrink-0" />
          <p>
            For a full WordPress-wide data export or erasure (covering all plugins), use WordPress's built-in{' '}
            <a href={`${adminUrl}export-personal-data.php`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-2 hover:text-primary">
              Export Personal Data <ExternalLink className="size-3" />
            </a>{' '}and{' '}
            <a href={`${adminUrl}erase-personal-data.php`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-2 hover:text-primary">
              Erase Personal Data <ExternalLink className="size-3" />
            </a>{' '}
            tools. Those tools only accept email addresses.
          </p>
        </div>

        {lookup && !hasData && (
          <Card>
            <CardContent className="py-8 text-center text-muted-foreground">
              No data found for this identifier.
            </CardContent>
          </Card>
        )}

        {hasData && (
          <>
            {lookup.contacts.map((contact) => (
              <Card key={contact.id}>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-base">
                    <User className="size-4" />
                    {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || 'Unnamed Contact'}
                    <Badge variant="outline" className="ml-auto font-normal">
                      {contact.status}
                    </Badge>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    {contact.email && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Mail className="size-3.5" />
                        <span>{contact.email}</span>
                      </div>
                    )}
                    {contact.phone && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Phone className="size-3.5" />
                        <span>{contact.phone}</span>
                      </div>
                    )}
                    {contact.source && (
                      <div className="text-muted-foreground">
                        Source: {contact.source}
                      </div>
                    )}
                    <div className="text-muted-foreground">
                      Created: {new Date(contact.created_at).toLocaleDateString()}
                    </div>
                  </div>

                  {contact.tags.length > 0 && (
                    <div className="flex items-center gap-2 flex-wrap">
                      <Tag className="size-3.5 text-muted-foreground" />
                      {contact.tags.map((tag) => (
                        <Badge key={tag.id} variant="secondary" className="text-xs">
                          {tag.name}
                        </Badge>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}

            {lookup.wp_user && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{__('WordPress User', 'wp-sms')}</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground">
                  {lookup.wp_user.display_name} ({lookup.wp_user.email}) — ID #{lookup.wp_user.id}
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader>
                <CardTitle className="text-base">{__('Data Summary', 'wp-sms')}</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-3 gap-4 text-center text-sm">
                  <div>
                    <div className="text-2xl font-bold">{lookup.contacts.length}</div>
                    <div className="text-muted-foreground">Contact(s)</div>
                  </div>
                  <div>
                    <div className="text-2xl font-bold">{lookup.message_log_count}</div>
                    <div className="text-muted-foreground">Message Logs</div>
                  </div>
                  <div>
                    <div className="text-2xl font-bold">{lookup.auth_log_count}</div>
                    <div className="text-muted-foreground">Auth Logs</div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Separator />

            <div className="space-y-3">
              <div className="flex gap-3">
                <Button variant="outline" onClick={handleExport} disabled={exporting}>
                  {exporting ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                  Export Data
                </Button>
                <Button variant="destructive" onClick={handleErase} disabled={erasing}>
                  {erasing ? <Loader2 className="size-4 animate-spin" /> : <Trash2 className="size-4" />}
                  Erase Data
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                <strong>Export</strong> downloads a JSON file containing contact profiles, tags, and message history.{' '}
                <strong>Erase</strong> permanently deletes contact records and tags, anonymizes message logs (campaign statistics are preserved), and removes authentication logs.
              </p>
            </div>
          </>
        )}
      </div>
    </>
  );
}
