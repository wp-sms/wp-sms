import { useState } from 'react';
import type { UseTagsReturn } from '@/hooks/use-tags';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TagForm } from './tag-form';
import { Plus, Tags, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useDeleteConfirm } from '@/hooks/use-delete-confirm';

interface TagsListProps {
  hook: UseTagsReturn;
}

export function TagsList({ hook }: TagsListProps) {
  const { tags, loading, createTag, updateTag, deleteTag } = hook;
  const [editing, setEditing] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);

  const handleCreate = async (data: { name: string; slug: string; color: string }) => {
    await createTag(data);
    setCreating(false);
    toast.success('Tag created.');
  };

  const handleUpdate = async (id: string, data: { name: string; slug: string; color: string }) => {
    await updateTag(id, data);
    setEditing(null);
    toast.success('Tag updated.');
  };

  const { handleDelete, isConfirming } = useDeleteConfirm(async (id) => {
    await deleteTag(id);
    toast.success('Tag deleted.');
  });

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2 text-base">
              <Tags className="h-4 w-4 text-muted-foreground" />
              Tags
            </CardTitle>
            <CardDescription>{tags.length} {tags.length === 1 ? 'tag' : 'tags'}</CardDescription>
          </div>
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {creating && (
          <div className="mb-4 rounded-lg border p-3">
            <TagForm onSave={handleCreate} onCancel={() => setCreating(false)} />
          </div>
        )}

        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
          </div>
        ) : tags.length === 0 && !creating ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
              <Tags className="h-5 w-5 text-muted-foreground" />
            </div>
            <p className="text-sm font-medium">No tags yet</p>
            <p className="mt-1 text-xs text-muted-foreground">Create tags to organize your contacts.</p>
            <Button size="sm" className="mt-4" onClick={() => setCreating(true)}>
              <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
            </Button>
          </div>
        ) : (
          <div className="rounded-lg border border-border/50 overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Color</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Contacts</TableHead>
                  <TableHead className="w-20">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tags.map((tag) => (
                  <TableRow key={tag.id} className="even:bg-muted/30">
                    {editing === tag.id ? (
                      <TableCell colSpan={5}>
                        <TagForm
                          initial={{ name: tag.name, slug: tag.slug, color: tag.color }}
                          onSave={(data) => void handleUpdate(tag.id, data)}
                          onCancel={() => setEditing(null)}
                        />
                      </TableCell>
                    ) : (
                      <>
                        <TableCell>
                          <span className="inline-block h-4 w-4 rounded-full" style={{ backgroundColor: tag.color }} />
                        </TableCell>
                        <TableCell className="font-medium">{tag.name}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">{tag.slug}</TableCell>
                        <TableCell className="text-sm">{tag.contact_count ?? 0}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => setEditing(tag.id)}>
                              <Pencil className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                              onClick={() => void handleDelete(tag.id)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
