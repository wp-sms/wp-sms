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
  unsubscribed: 'neutral',
  pending: 'warning',
  bounced: 'warning',
  complained: 'destructive',
};

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
      title: 'Delete contact?',
      description: 'This contact and all associated data will be permanently removed.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
    await deleteContact(id);
    toast.success('Contact deleted.');
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
      toast.success('Contact updated.');
    } else {
      await createContact(data);
      toast.success('Contact created.');
    }
  };

  const handleBulkAction = async (action: string, params?: Record<string, unknown>) => {
    try {
      await bulkAction(action, selectedIds, params);
      toast.success(`Bulk ${action} completed.`);
    } catch {
      toast.error(`Bulk ${action} failed.`);
    }
  };

  const tableContent = (
    <>
      {/* Filters */}
      <div className="mb-4 flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            className="pl-8 h-9"
            placeholder="Search contacts..."
            value={filters.search}
            onChange={(e) => setFilter('search', e.target.value)}
          />
        </div>
        <Select value={filters.status || 'all'} onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}>
          <SelectTrigger className="w-40 h-9">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
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
            title="No contacts found"
            description="Add your first contact to get started."
            action={
              <Button size="sm" onClick={handleCreate}>
                <Plus className="mr-1.5 h-3.5 w-3.5" /> New Contact
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
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Phone</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Tags</TableHead>
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
                </TableCell>
                <TableCell className="text-sm">
                  {contact.tags?.length
                    ? <TagChips tags={contact.tags} maxItems={2} size="sm" />
                    : '\u2014'}
                </TableCell>
                <ActionsCell>
                  <DropdownMenuItem onClick={() => handleViewDetail(contact.id)}>
                    <Eye className="h-4 w-4 mr-2" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleEdit(contact)}>
                    <Pencil className="h-4 w-4 mr-2" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => void handleDelete(contact.id)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
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
                  Contacts
                </CardTitle>
                <CardDescription>{total} {total === 1 ? 'contact' : 'contacts'} total</CardDescription>
              </div>
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" onClick={onImport}>
                  <Upload className="mr-1.5 h-3.5 w-3.5" /> Import
                </Button>
                <ExportDialog onExport={hook.exportContacts}>
                  <Button variant="outline" size="sm">
                    <Download className="mr-1.5 h-3.5 w-3.5" /> Export
                  </Button>
                </ExportDialog>
                <Button size="sm" onClick={handleCreate}>
                  <Plus className="mr-1.5 h-3.5 w-3.5" /> New Contact
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
