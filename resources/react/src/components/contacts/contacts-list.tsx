import { useState } from 'react';
import type { Contact, Tag } from '@/lib/api';
import type { UseContactsReturn } from '@/hooks/use-contacts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageNumbers } from '@/components/ui/pagination';
import { ContactFormSheet } from './contact-form-sheet';
import { ContactDetailSheet } from './contact-detail-sheet';
import { BulkActionBar } from './bulk-action-bar';
import { ExportDialog } from './export-dialog';
import { Plus, Search, Users, Pencil, Trash2, Eye, Upload, Download } from 'lucide-react';
import { CONTACT_STATUSES, formatLabel } from '@/lib/constants';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

interface ContactsListProps {
  hook: UseContactsReturn;
  tags: Tag[];
  onImport: () => void;
}

const STATUS_STYLES: Record<string, string> = {
  subscribed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  unsubscribed: 'border-gray-200 bg-gray-50 text-gray-600',
  bounced: 'border-amber-200 bg-amber-50 text-amber-700',
  complained: 'border-red-200 bg-red-50 text-red-700',
};

export function ContactsList({ hook, tags, onImport }: ContactsListProps) {
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

  const handleCreate = () => {
    setEditContact(null);
    setFormOpen(true);
  };

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

  return (
    <>
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
          {loading ? (
            <div className="space-y-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : contacts.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-center">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
                <Users className="h-5 w-5 text-muted-foreground" />
              </div>
              <p className="text-sm font-medium">No contacts found</p>
              <p className="mt-1 text-xs text-muted-foreground">Add your first contact to get started.</p>
              <Button size="sm" className="mt-4" onClick={handleCreate}>
                <Plus className="mr-1.5 h-3.5 w-3.5" /> New Contact
              </Button>
            </div>
          ) : (
            <>
              <div className="rounded-lg border border-border/50 overflow-hidden">
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
                      <TableHead>Source</TableHead>
                      <TableHead className="w-24">Actions</TableHead>
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
                        <TableCell>
                          <button
                            type="button"
                            className="font-medium hover:text-primary hover:underline text-left"
                            onClick={() => handleViewDetail(contact.id)}
                          >
                            {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || '\u2014'}
                          </button>
                        </TableCell>
                        <TableCell className="text-sm">{contact.email || '\u2014'}</TableCell>
                        <TableCell className="text-sm">{contact.phone || '\u2014'}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className={STATUS_STYLES[contact.status] || ''}>
                            {formatLabel(contact.status)}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">{contact.source || '\u2014'}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => handleViewDetail(contact.id)} title="View">
                              <Eye className="h-3.5 w-3.5" />
                            </Button>
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => handleEdit(contact)} title="Edit">
                              <Pencil className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                              onClick={() => void handleDelete(contact.id)}
                              title="Delete"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <PageNumbers page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardContent>
      </Card>

      {/* Bulk action bar */}
      {selectedIds.length > 0 && (
        <BulkActionBar
          count={selectedIds.length}
          tags={tags}
          onBulkAction={handleBulkAction}
          onClear={clearSelection}
        />
      )}

      {/* Form sheet */}
      <ContactFormSheet
        open={formOpen}
        onOpenChange={setFormOpen}
        contact={editContact}
        onSave={handleSave}
      />

      {/* Detail sheet */}
      <ContactDetailSheet
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
