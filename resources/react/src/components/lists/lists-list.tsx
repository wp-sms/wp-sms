import { useState } from 'react';
import type { ContactList, Tag } from '@/lib/api';
import type { UseListsReturn } from '@/hooks/use-lists';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ListFormSheet } from './list-form-sheet';
import { Plus, List, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useDeleteConfirm } from '@/hooks/use-delete-confirm';

interface ListsListProps {
  hook: UseListsReturn;
  tags: Tag[];
}

export function ListsList({ hook, tags }: ListsListProps) {
  const { lists, loading, createList, updateList, deleteList } = hook;
  const [formOpen, setFormOpen] = useState(false);
  const [editList, setEditList] = useState<ContactList | null>(null);

  const handleCreate = () => {
    setEditList(null);
    setFormOpen(true);
  };

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

  const { handleDelete, isConfirming } = useDeleteConfirm(async (id) => {
    await deleteList(id);
    toast.success('List deleted.');
  });

  return (
    <>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <List className="h-4 w-4 text-muted-foreground" />
                Lists & Segments
              </CardTitle>
              <CardDescription>{lists.length} {lists.length === 1 ? 'list' : 'lists'}</CardDescription>
            </div>
            <Button size="sm" onClick={handleCreate}>
              <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : lists.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-center">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
                <List className="h-5 w-5 text-muted-foreground" />
              </div>
              <p className="text-sm font-medium">No lists yet</p>
              <p className="mt-1 text-xs text-muted-foreground">Create lists to segment your contacts.</p>
              <Button size="sm" className="mt-4" onClick={handleCreate}>
                <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
              </Button>
            </div>
          ) : (
            <div className="rounded-lg border border-border/50 overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Contacts</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead className="w-20">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {lists.map((list) => (
                    <TableRow key={list.id} className="even:bg-muted/30">
                      <TableCell className="font-medium">{list.name}</TableCell>
                      <TableCell>
                        {list.type === 'dynamic' ? (
                          <Badge variant="outline" className="border-blue-200 bg-blue-50 text-blue-700">Dynamic</Badge>
                        ) : (
                          <Badge variant="outline" className="border-purple-200 bg-purple-50 text-purple-700">Static</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-sm">{list.contact_count}</TableCell>
                      <TableCell className="text-sm text-muted-foreground max-w-[200px] truncate">
                        {list.description || '\u2014'}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1">
                          <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => handleEdit(list)}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                            onClick={() => void handleDelete(list.id)}
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
          )}
        </CardContent>
      </Card>

      <ListFormSheet
        open={formOpen}
        onOpenChange={setFormOpen}
        list={editList}
        tags={tags}
        onSave={handleSave}
      />
    </>
  );
}
