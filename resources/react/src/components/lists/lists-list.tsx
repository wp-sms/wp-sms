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
      toast.success('List updated.');
    } else {
      await createList(data);
      toast.success('List created.');
    }
  };

  const confirm = useConfirm();

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: 'Delete list?',
      description: 'This list will be permanently removed. Contacts in this list will not be deleted.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
    await deleteList(id);
    toast.success('List deleted.');
  };

  const tableContent = (
    <DataTable
      loading={loading}
      skeletonRows={3}
      isEmpty={lists.length === 0}
      empty={
        <EmptyState
          icon={List}
          title="No lists yet"
          description="Create lists to segment your contacts."
          action={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
            </Button>
          }
        />
      }
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Contacts</TableHead>
            <TableHead>Description</TableHead>
            <TableHead className="w-[70px]" />
          </TableRow>
        </TableHeader>
        <TableBody>
          {lists.map((list) => (
            <TableRow key={list.id} className="even:bg-muted/30">
              <NameCell onClick={() => handleEdit(list)}>{list.name}</NameCell>
              <TableCell>
                <Badge variant={list.type === 'dynamic' ? 'info' : 'purple'}>
                  {list.type === 'dynamic' ? 'Dynamic' : 'Static'}
                </Badge>
              </TableCell>
              <TableCell className="text-sm">{list.contact_count}</TableCell>
              <TableCell className="text-sm text-muted-foreground max-w-[200px] truncate">
                {list.description || '\u2014'}
              </TableCell>
              <ActionsCell>
                <DropdownMenuItem onClick={() => handleEdit(list)}>
                  <Pencil className="h-4 w-4 mr-2" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => void handleDelete(list.id)}
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
  );

  return (
    <>
      {embedded ? (
        tableContent
      ) : (
        <PageSection
          icon={List}
          title="Lists & Segments"
          description={pluralize(lists.length, 'list')}
          actions={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
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
