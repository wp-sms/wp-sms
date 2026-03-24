import { useState } from 'react';
import type { UseTagsReturn } from '@/hooks/use-tags';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageSection } from '@/components/ui/page-section';
import { TagForm } from './tag-form';
import { Plus, Tags, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

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

  const confirm = useConfirm();

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: 'Delete tag?',
      description: 'This tag will be removed from all contacts.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
    await deleteTag(id);
    toast.success('Tag deleted.');
  };

  return (
    <PageSection
      icon={Tags}
      title="Tags"
      description={<>{tags.length} {tags.length === 1 ? 'tag' : 'tags'}</>}
      actions={
        <Button size="sm" onClick={() => setCreating(true)}>
          <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
        </Button>
      }
    >
        {creating && (
          <div className="mb-4 rounded-lg border p-3">
            <TagForm onSave={handleCreate} onCancel={() => setCreating(false)} />
          </div>
        )}

        <DataTable
          loading={loading}
          skeletonRows={3}
          isEmpty={tags.length === 0 && !creating}
          empty={
            <EmptyState
              icon={Tags}
              title="No tags yet"
              description="Create tags to organize your contacts."
              action={
                <Button size="sm" onClick={() => setCreating(true)}>
                  <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
                </Button>
              }
            />
          }
        >
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
        </DataTable>
    </PageSection>
  );
}
