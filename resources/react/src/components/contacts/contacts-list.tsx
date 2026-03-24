import { useState } from 'react';
import type { Contact, Tag } from '@/lib/api';
import type { UseContactsReturn } from '@/hooks/use-contacts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { ContactFormPanel } from './contact-form-panel';
import { ContactDetailPanel } from './contact-detail-panel';
import { BulkActionBar } from './bulk-action-bar';
import { ExportDialog } from './export-dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, Search, Users, Pencil, Trash2, Eye, Upload, Download, MoreHorizontal } from 'lucide-react';
import { CONTACT_STATUSES, formatLabel } from '@/lib/constants';
import { SourceLabel } from './source-label';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

interface ContactsListProps {
  hook: UseContactsReturn;
  tags: Tag[];
  onImport: () => void;
}

const STATUS_VARIANTS: Record<string, 'success' | 'neutral' | 'warning' | 'destructive'> = {
  subscribed: 'success',
  unsubscribed: 'neutral',
  pending: 'warning',
  bounced: 'warning',
  complained: 'destructive',
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
                  <TableHead>Source</TableHead>
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
                    <TableCell>
                      <button
                        type="button"
                        className="inline-flex items-center gap-2 font-medium text-left transition-colors hover:text-primary"
                        onClick={() => handleViewDetail(contact.id)}
                      >
                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">
                          {(contact.first_name?.[0] || contact.email?.[0] || '?').toUpperCase()}
                        </span>
                        {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || '\u2014'}
                      </button>
                    </TableCell>
                    <TableCell className="text-sm">{contact.email || '\u2014'}</TableCell>
                    <TableCell className="text-sm">{contact.phone || '\u2014'}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANTS[contact.status] || 'neutral'}>
                        {formatLabel(contact.status)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {contact.source ? <SourceLabel source={contact.source} sourceRef={contact.source_ref} showPrefix={false} /> : '\u2014'}
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
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
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </DataTable>
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
