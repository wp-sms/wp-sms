import { useState } from 'react';
import { useFlows } from '@/hooks/use-flows';
import { FlowEditor } from './flow-editor';
import type { Flow } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Field, FieldLabel } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import { Plus, Workflow, Pencil, Trash2, Rocket } from 'lucide-react';
import { formatLabel } from '@/lib/constants';
import { toast } from 'sonner';

type View = { mode: 'list' } | { mode: 'create' } | { mode: 'edit'; flow: Flow };

export function Flows() {
  const { flows, total, page, perPage, filters, setFilter, setPage, loading, createFlow, updateFlow, deleteFlow, publishFlow } = useFlows();
  const [view, setView] = useState<View>({ mode: 'list' });
  const [deleting, setDeleting] = useState<string | null>(null);
  const [publishing, setPublishing] = useState<string | null>(null);

  const totalPages = Math.ceil(total / perPage);

  const handleDelete = async (id: string) => {
    setDeleting(id);
    try {
      await deleteFlow(id);
      toast.success('Flow deleted.');
    } catch {
      toast.error('Failed to delete flow.');
    } finally {
      setDeleting(null);
    }
  };

  const handlePublish = async (id: string) => {
    setPublishing(id);
    try {
      await publishFlow(id);
      toast.success('Flow published.');
    } catch {
      toast.error('Failed to publish flow.');
    } finally {
      setPublishing(null);
    }
  };

  if (view.mode === 'create') {
    return (
      <FlowEditor
        onSave={createFlow}
        onBack={() => setView({ mode: 'list' })}
      />
    );
  }

  if (view.mode === 'edit') {
    return (
      <FlowEditor
        flow={view.flow}
        onSave={(data) => updateFlow(view.flow.id, data)}
        onPublish={publishFlow}
        onBack={() => setView({ mode: 'list' })}
      />
    );
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <Workflow className="h-4 w-4 text-muted-foreground" />
                Automation Flows
              </CardTitle>
              <CardDescription>
                {total} {total === 1 ? 'flow' : 'flows'} total
              </CardDescription>
            </div>
            <Button size="sm" onClick={() => setView({ mode: 'create' })}>
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              New Flow
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="mb-4">
            <Field>
              <FieldLabel htmlFor="filter-status">Status</FieldLabel>
              <Select
                value={filters.status || 'all'}
                onValueChange={(v) => setFilter('status', v === 'all' ? '' : v)}
              >
                <SelectTrigger id="filter-status" className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </div>

          {loading ? (
            <div className="space-y-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : flows.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-center">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
                <Workflow className="h-5 w-5 text-muted-foreground" />
              </div>
              <p className="text-sm font-medium">No flows found</p>
              <p className="mt-1 text-xs text-muted-foreground">Create your first automation flow to get started.</p>
              <Button size="sm" className="mt-4" onClick={() => setView({ mode: 'create' })}>
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                New Flow
              </Button>
            </div>
          ) : (
            <>
              <div className="rounded-lg border border-border/50 overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Trigger</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Published</TableHead>
                      <TableHead className="w-24">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {flows.map((flow) => (
                      <TableRow key={flow.id} className="even:bg-muted/30">
                        <TableCell className="font-medium">{flow.name}</TableCell>
                        <TableCell className="text-sm">{formatLabel(flow.trigger_type)}</TableCell>
                        <TableCell>
                          {flow.status === 'published' ? (
                            <Badge variant="outline" className="border-emerald-200 bg-emerald-50 text-emerald-700">
                              Published
                            </Badge>
                          ) : (
                            <Badge variant="secondary">Draft</Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-sm">
                          {flow.published_at ? new Date(flow.published_at).toLocaleDateString() : '\u2014'}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0"
                              onClick={() => setView({ mode: 'edit', flow })}
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </Button>
                            {flow.status === 'draft' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 p-0"
                                onClick={() => void handlePublish(flow.id)}
                                disabled={publishing === flow.id}
                              >
                                <Rocket className="h-3.5 w-3.5" />
                              </Button>
                            )}
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                              onClick={() => void handleDelete(flow.id)}
                              disabled={deleting === flow.id}
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

              {totalPages > 1 && (
                <div className="mt-4 flex justify-center">
                  <Pagination>
                    <PaginationContent>
                      <PaginationItem>
                        <PaginationPrevious
                          onClick={() => setPage(Math.max(1, page - 1))}
                          className={page <= 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                        />
                      </PaginationItem>
                      {Array.from({ length: Math.min(5, totalPages) }).map((_, i) => {
                        let pageNum: number;
                        if (totalPages <= 5) {
                          pageNum = i + 1;
                        } else if (page <= 3) {
                          pageNum = i + 1;
                        } else if (page >= totalPages - 2) {
                          pageNum = totalPages - 4 + i;
                        } else {
                          pageNum = page - 2 + i;
                        }
                        return (
                          <PaginationItem key={pageNum}>
                            <PaginationLink
                              onClick={() => setPage(pageNum)}
                              isActive={page === pageNum}
                              className="cursor-pointer"
                            >
                              {pageNum}
                            </PaginationLink>
                          </PaginationItem>
                        );
                      })}
                      <PaginationItem>
                        <PaginationNext
                          onClick={() => setPage(Math.min(totalPages, page + 1))}
                          className={page >= totalPages ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                        />
                      </PaginationItem>
                    </PaginationContent>
                  </Pagination>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
