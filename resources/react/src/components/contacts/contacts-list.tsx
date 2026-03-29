import { __, sprintf } from '@wordpress/i18n';
import { useState, useCallback } from 'react';
import type { Contact, Tag } from '@/lib/api';
import type { UseContactsReturn } from '@/hooks/use-contacts';
import { useCreateTrigger } from '@/hooks/use-create-trigger';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { NameCell } from '@/components/ui/name-cell';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { ContactFormPanel } from './contact-form-panel';
import { ContactDetailPanel } from './contact-detail-panel';
import { BulkActionBar } from './bulk-action-bar';
import { ExportDialog } from './export-dialog';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Plus, Search, Users, Pencil, Trash2, Eye, Upload, Download } from 'lucide-react';
import { CONTACT_STATUSES, formatLabel } from '@/lib/constants';
import { TagChips } from './tag-chips';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

interface ContactsListProps {
  hook: UseContactsReturn;
  tags: Tag[];
  onImport: () => void;
  embedded?: boolean;
  createTrigger?: number;
}

const STATUS_VARIANTS: Record<string, 'success' | 'neutral' | 'warning' | 'destructive'> = {
  subscribed: 'success',
  pending: 'warning',
  bounced: 'warning',
  complained: 'destructive',
};

function OptOutIndicator({ optOuts }: { optOuts?: Record<string, string | null> }) {
  if (!optOuts) return null;
  const active = Object.entries(optOuts).filter(([, v]) => v != null);
  if (active.length === 0) return null;
  const labels = active.map(([ch]) => ch.toUpperCase()).join(', ');
  return (
    <span className="text-[11px] text-muted-foreground ms-1.5">
      ({labels} opted out)
    </span>
  );
}

export function ContactsList({ hook, tags, onImport, embedded, createTrigger }: ContactsListProps) {
  const {
    contacts, total, page, perPage, filters, setFilter, setPage, loading,
    createContact, updateContact, deleteContact, fetchContact,
    addTag, removeTag, bulkAction,
    selectedIds, toggleSelect, selectAll, clearSelection, isAllSelected,
  } = hook;

  const [formOpen, setFormOpen] = useState(false);
  const [editContact, setEditContact] = useState<Contact | null>(null);
  const [detailId, setDetailId] = useState<string | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);

  const confirm = useConfirm();

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: __('Delete contact?', 'wp-sms'),
      description: __('This contact and all associated data will be permanently removed.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    await deleteContact(id);
    toast.success(__('Contact deleted.', 'wp-sms'));
  };

  const totalPages = Math.ceil(total / perPage);

  const handleCreate = useCallback(() => {
    setEditContact(null);
    setFormOpen(true);
  }, []);

  useCreateTrigger(createTrigger, handleCreate);

  const handleEdit = (contact: Contact) => {
    setEditContact(contact);
    setFormOpen(true);
  };

  const handleViewDetail = (id: string) => {
    setDetailId(id);
    setDetailOpen(true);
  };

  const handleSave = async (data: Partial<Contact>) => {
    if (editContact) {
      await updateContact(editContact.id, data);
      toast.success(__('Contact updated.', 'wp-sms'));
    } else {
      await createContact(data);
      toast.success(__('Contact created.', 'wp-sms'));
    }
  };

  const handleBulkAction = async (action: string, params?: Record<string, unknown>) => {
    try {
      await bulkAction(action, selectedIds, params);
      toast.success(sprintf(__('Bulk %s completed.', 'wp-sms'), action));
    } catch {
      toast.error(sprintf(__('Bulk %s failed.', 'wp-sms'), action));
    }
  };

  const tableContent = (
    <>
      {/* Filters */}
      <div className="mb-4 flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            className="ps-8 h-9"
            placeholder={__('Search contacts...', 'wp-sms')}
            value={filters.search}
            onChange={(e) => setFilter('search', e.target.value)}
          />
        </div>
        <Select value={filters.status || 'all'} onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}>
          <SelectTrigger className="w-40 h-9">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{__('All statuses', 'wp-sms')}</SelectItem>
            {CONTACT_STATUSES.map((s) => (
              <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      <DataTable
        loading={loading}
        isEmpty={contacts.length === 0}
        empty={
          <EmptyState
            icon={Users}
            title={__('No contacts found', 'wp-sms')}
            description={__('Add your first contact to get started.', 'wp-sms')}
            action={
              <Button size="sm" onClick={handleCreate}>
                <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Contact', 'wp-sms')}
              </Button>
            }
          />
        }
        pagination={{ page, totalPages, onPageChange: setPage }}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">
                <Checkbox checked={isAllSelected} onCheckedChange={(checked) => checked ? selectAll() : clearSelection()} />
              </TableHead>
              <TableHead>{__('Name', 'wp-sms')}</TableHead>
              <TableHead>{__('Email', 'wp-sms')}</TableHead>
              <TableHead>{__('Phone', 'wp-sms')}</TableHead>
              <TableHead>{__('Status', 'wp-sms')}</TableHead>
              <TableHead>{__('Tags', 'wp-sms')}</TableHead>
              <TableHead className="w-[70px]" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {contacts.map((contact) => (
              <TableRow key={contact.id} className="even:bg-muted/30">
                <TableCell>
                  <Checkbox
                    checked={selectedIds.includes(contact.id)}
                    onCheckedChange={() => toggleSelect(contact.id)}
                  />
                </TableCell>
                <NameCell onClick={() => handleViewDetail(contact.id)}>
                  {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || '\u2014'}
                </NameCell>
                <TableCell className="text-sm">{contact.email || '\u2014'}</TableCell>
                <TableCell className="text-sm">{contact.phone || '\u2014'}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANTS[contact.status] || 'neutral'}>
                    {formatLabel(contact.status)}
                  </Badge>
                  <OptOutIndicator optOuts={contact.channel_opt_outs} />
                </TableCell>
                <TableCell className="text-sm">
                  {contact.tags?.length
                    ? <TagChips tags={contact.tags} maxItems={2} size="sm" />
                    : '\u2014'}
                </TableCell>
                <ActionsCell>
                  <DropdownMenuItem onClick={() => handleViewDetail(contact.id)}>
                    <Eye className="h-4 w-4 me-2" />
                    {__('View', 'wp-sms')}
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleEdit(contact)}>
                    <Pencil className="h-4 w-4 me-2" />
                    {__('Edit', 'wp-sms')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => void handleDelete(contact.id)}
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
    </>
  );

  return (
    <>
      {embedded ? (
        tableContent
      ) : (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Users className="h-4 w-4 text-muted-foreground" />
                  {__('Contacts', 'wp-sms')}
                </CardTitle>
                <CardDescription>{sprintf(total === 1 ? __('%d contact total', 'wp-sms') : __('%d contacts total', 'wp-sms'), total)}</CardDescription>
              </div>
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" onClick={onImport}>
                  <Upload className="me-1.5 h-3.5 w-3.5" /> {__('Import', 'wp-sms')}
                </Button>
                <ExportDialog onExport={hook.exportContacts}>
                  <Button variant="outline" size="sm">
                    <Download className="me-1.5 h-3.5 w-3.5" /> {__('Export', 'wp-sms')}
                  </Button>
                </ExportDialog>
                <Button size="sm" onClick={handleCreate}>
                  <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Contact', 'wp-sms')}
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {tableContent}
          </CardContent>
        </Card>
      )}

      {/* Bulk action bar */}
      {selectedIds.length > 0 && (
        <BulkActionBar
          count={selectedIds.length}
          tags={tags}
          onBulkAction={handleBulkAction}
          onClear={clearSelection}
        />
      )}

      {/* Form panel */}
      <ContactFormPanel
        open={formOpen}
        onOpenChange={setFormOpen}
        contact={editContact}
        onSave={handleSave}
      />

      {/* Detail panel */}
      <ContactDetailPanel
        open={detailOpen}
        onOpenChange={setDetailOpen}
        contactId={detailId}
        fetchContact={fetchContact}
        allTags={tags}
        onAddTag={addTag}
        onRemoveTag={removeTag}
      />
    </>
  );
}
