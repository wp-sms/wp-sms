import { __ } from '@wordpress/i18n';
import { useState, useCallback } from 'react';
import type { Tag } from '@/lib/api';
import type { UseTagsReturn } from '@/hooks/use-tags';
import { useCreateTrigger } from '@/hooks/use-create-trigger';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageSection } from '@/components/ui/page-section';
import { TagFormPanel } from './tag-form-panel';
import { NameCell } from '@/components/ui/name-cell';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Plus, Tags, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';
import { pluralize } from '@/lib/utils';

interface TagsListProps {
  hook: UseTagsReturn;
  embedded?: boolean;
  createTrigger?: number;
}

export function TagsList({ hook, embedded, createTrigger }: TagsListProps) {
  const { tags, loading, createTag, updateTag, deleteTag } = hook;
  const [formOpen, setFormOpen] = useState(false);
  const [editTag, setEditTag] = useState<Tag | null>(null);

  const handleCreate = useCallback(() => {
    setEditTag(null);
    setFormOpen(true);
  }, []);

  useCreateTrigger(createTrigger, handleCreate);

  const handleEdit = (tag: Tag) => {
    setEditTag(tag);
    setFormOpen(true);
  };

  const handleSave = async (data: { name: string; slug: string; color: string }) => {
    if (editTag) {
      await updateTag(editTag.id, data);
      toast.success(__('Tag updated.', 'wp-sms'));
    } else {
      await createTag(data);
      toast.success(__('Tag created.', 'wp-sms'));
    }
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
    toast.success(__('Tag deleted.', 'wp-sms'));
  };

  const tableContent = (
    <DataTable
      loading={loading}
      skeletonRows={3}
      isEmpty={tags.length === 0}
      empty={
        <EmptyState
          icon={Tags}
          title={__('No tags yet', 'wp-sms')}
          description={__('Create tags to organize your contacts.', 'wp-sms')}
          action={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Tag', 'wp-sms')}
            </Button>
          }
        />
      }
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{__('Color', 'wp-sms')}</TableHead>
            <TableHead>{__('Name', 'wp-sms')}</TableHead>
            <TableHead>{__('Slug', 'wp-sms')}</TableHead>
            <TableHead>{__('Contacts', 'wp-sms')}</TableHead>
            <TableHead className="w-[70px]" />
          </TableRow>
        </TableHeader>
        <TableBody>
          {tags.map((tag) => (
            <TableRow key={tag.id} className="even:bg-muted/30">
              <TableCell>
                <span className="inline-block h-4 w-4 rounded-full" style={{ backgroundColor: tag.color }} />
              </TableCell>
              <NameCell onClick={() => handleEdit(tag)}>{tag.name}</NameCell>
              <TableCell className="text-sm text-muted-foreground">{tag.slug}</TableCell>
              <TableCell className="text-sm">{tag.contact_count ?? 0}</TableCell>
              <ActionsCell>
                <DropdownMenuItem onClick={() => handleEdit(tag)}>
                  <Pencil className="h-4 w-4 me-2" />
                  {__('Edit', 'wp-sms')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => void handleDelete(tag.id)}
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
          icon={Tags}
          title={__('Tags', 'wp-sms')}
          description={pluralize(tags.length, 'tag')}
          actions={
            <Button size="sm" onClick={handleCreate}>
              <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Tag', 'wp-sms')}
            </Button>
          }
        >
          {tableContent}
        </PageSection>
      )}

      <TagFormPanel
        open={formOpen}
        onOpenChange={setFormOpen}
        tag={editTag}
        onSave={handleSave}
      />
    </>
  );
}
