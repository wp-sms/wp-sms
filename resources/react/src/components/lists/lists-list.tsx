import { __ } from '@wordpress/i18n';
import { useState, useCallback } from 'react';
import type { ContactList, Tag } from '@/lib/api';
import type { UseListsReturn } from '@/hooks/use-lists';
import { useCreateTrigger } from '@/hooks/use-create-trigger';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageSection } from '@/components/ui/page-section';
import { ListFormPanel } from './list-form-panel';
import { NameCell } from '@/components/ui/name-cell';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Plus, List, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';
import { pluralize } from '@/lib/utils';

interface ListsListProps {
  hook: UseListsReturn;
  tags: Tag[];
  embedded?: boolean;
  createTrigger?: number;
}

export function ListsList({ hook, tags, embedded, createTrigger }: ListsListProps) {
  const { lists, loading, createList, updateList, deleteList } = hook;
  const [formOpen, setFormOpen] = useState(false);
  const [editList, setEditList] = useState<ContactList | null>(null);

  const handleCreate = useCallback(() => {
    setEditList(null);
    setFormOpen(true);
  }, []);

  useCreateTrigger(createTrigger, handleCreate);

  const handleEdit = (list: ContactList) => {
    setEditList(list);
    setFormOpen(true);
  };

  const handleSave = async (data: Partial<ContactList>) => {
    if (editList) {
      await updateList(editList.id, data);
      toast.success(__('List updated.', 'wp-sms'));
    } else {
      await createList(data);
      toast.success(__('List created.', 'wp-sms'));
    }
  };

  const confirm = useConfirm();

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: __('Delete list?', 'wp-sms'),
      description: __('This list will be permanently removed. Contacts in this list will not be deleted.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    await deleteList(id);
    toast.success(__('List deleted.', 'wp-sms'));
  };

  const tableContent = (
    <DataTable
      loading={loading}
      skeletonRows={3}
      isEmpty={lists.length === 0}
      empty={
        <EmptyState
          icon={List}
          title={__('No lists yet', 'wp-sms')}
          description={__('Create lists to segment your contacts.', 'wp-sms')}
          action={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New List', 'wp-sms')}
            </Button>
          }
        />
      }
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{__('Name', 'wp-sms')}</TableHead>
            <TableHead>{__('Type', 'wp-sms')}</TableHead>
            <TableHead>{__('Contacts', 'wp-sms')}</TableHead>
            <TableHead>{__('Description', 'wp-sms')}</TableHead>
            <TableHead className="w-[70px]" />
          </TableRow>
        </TableHeader>
        <TableBody>
          {lists.map((list) => (
            <TableRow key={list.id} className="even:bg-muted/30">
              <NameCell onClick={() => handleEdit(list)}>{list.name}</NameCell>
              <TableCell>
                <Badge variant={list.type === 'dynamic' ? 'info' : 'purple'}>
                  {list.type === 'dynamic' ? __('Dynamic', 'wp-sms') : __('Static', 'wp-sms')}
                </Badge>
              </TableCell>
              <TableCell className="text-sm">{list.contact_count}</TableCell>
              <TableCell className="text-sm text-muted-foreground max-w-[200px] truncate">
                {list.description || '\u2014'}
              </TableCell>
              <ActionsCell>
                <DropdownMenuItem onClick={() => handleEdit(list)}>
                  <Pencil className="h-4 w-4 me-2" />
                  {__('Edit', 'wp-sms')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => void handleDelete(list.id)}
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
  );

  return (
    <>
      {embedded ? (
        tableContent
      ) : (
        <PageSection
          icon={List}
          title={__('Lists & Segments', 'wp-sms')}
          description={pluralize(lists.length, 'list')}
          actions={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New List', 'wp-sms')}
            </Button>
          }
        >
          {tableContent}
        </PageSection>
      )}

      <ListFormPanel
        open={formOpen}
        onOpenChange={setFormOpen}
        list={editList}
        tags={tags}
        onSave={handleSave}
      />
    </>
  );
}
