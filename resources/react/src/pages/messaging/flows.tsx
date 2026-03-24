import { useState, useEffect, useMemo } from 'react';
import { useFlows } from '@/hooks/use-flows';
import { FlowEditor } from './flow-editor';
import type { Flow, FlowTemplate } from '@/lib/api';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageSection } from '@/components/ui/page-section';
import { Field, FieldLabel } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Plus, Workflow, Pencil, Trash2, Rocket, LayoutTemplate, Search,
  ShoppingCart, Users, MessageSquare, FileText, History, Pause,
  type LucideIcon,
} from 'lucide-react';
import { formatLabel } from '@/lib/constants';
import { countSteps } from '@/lib/flow-utils';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';

type View =
  | { mode: 'list' }
  | { mode: 'create' }
  | { mode: 'create-from-template'; template: FlowTemplate }
  | { mode: 'edit'; flow: Flow; tab?: string };

function getTriggerIcon(triggerType: string): LucideIcon {
  if (triggerType.startsWith('woocommerce.')) return ShoppingCart;
  if (triggerType.startsWith('wordpress.user_')) return Users;
  if (triggerType.startsWith('wordpress.comment_')) return MessageSquare;
  if (triggerType.startsWith('wordpress.post_')) return FileText;
  return Workflow;
}

export function Flows() {
  const { flows, total, page, perPage, filters, setFilter, setPage, loading, createFlow, updateFlow, deleteFlow, publishFlow } = useFlows();
  const [view, setView] = useState<View>({ mode: 'list' });
  const [deleting, setDeleting] = useState<string | null>(null);
  const [publishing, setPublishing] = useState<string | null>(null);
  const [deactivating, setDeactivating] = useState<string | null>(null);
  const [templates, setTemplates] = useState<FlowTemplate[]>([]);
  const [templatesLoading, setTemplatesLoading] = useState(true);
  const [showTemplates, setShowTemplates] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('all');

  const confirm = useConfirm();
  const totalPages = Math.ceil(total / perPage);

  // Load templates
  useEffect(() => {
    const controller = new AbortController();
    api.get<{ items: FlowTemplate[] }>('flows/templates', { signal: controller.signal })
      .then((res) => { setTemplates(res.items); setTemplatesLoading(false); })
      .catch((e) => {
        if (!(e instanceof DOMException && e.name === 'AbortError')) setTemplatesLoading(false);
      });
    return () => { controller.abort(); };
  }, []);

  // Derive categories from template data
  const categories = useMemo(() => {
    const cats = new Set(templates.map((t) => t.category));
    return Array.from(cats).sort();
  }, [templates]);

  // Filter templates
  const filteredTemplates = useMemo(() => {
    let result = templates;
    if (activeCategory !== 'all') {
      result = result.filter((t) => t.category === activeCategory);
    }
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter(
        (t) => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q),
      );
    }
    return result;
  }, [templates, activeCategory, searchQuery]);

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: 'Delete flow?',
      description: 'This automation flow and its execution history will be permanently removed.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
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
    const ok = await confirm({
      title: 'Publish flow?',
      description: 'This flow will become active and start processing events immediately.',
      confirmLabel: 'Publish',
    });
    if (!ok) return;
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

  const handleDeactivate = async (id: string) => {
    setDeactivating(id);
    try {
      await updateFlow(id, { status: 'paused' });
      toast.success('Flow deactivated.');
    } catch {
      toast.error('Failed to deactivate flow.');
    } finally {
      setDeactivating(null);
    }
  };

  const handleUseTemplate = (template: FlowTemplate) => {
    setShowTemplates(false);
    setView({ mode: 'create-from-template', template });
  };

  const handleOpenTemplates = () => {
    setSearchQuery('');
    setActiveCategory('all');
    setShowTemplates(true);
  };

  if (view.mode === 'create') {
    return (
      <FlowEditor
        onSave={createFlow}
        onBack={() => setView({ mode: 'list' })}
      />
    );
  }

  if (view.mode === 'create-from-template') {
    const t = view.template;
    return (
      <FlowEditor
        initialData={{
          name: t.name,
          description: t.description,
          trigger_type: t.trigger_type,
          trigger_config: t.trigger_config,
          steps: t.steps,
        }}
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
        onDeactivate={(id) => updateFlow(id, { status: 'paused' })}
        onBack={() => setView({ mode: 'list' })}
        defaultTab={view.tab}
      />
    );
  }

  return (
    <div className="space-y-4">
      <PageSection
        icon={Workflow}
        title="Automation Flows"
        description={<>{total} {total === 1 ? 'flow' : 'flows'} total</>}
        actions={
          <div className="flex items-center gap-2">
            {!templatesLoading && templates.length > 0 && (
              <Button variant="outline" size="sm" onClick={handleOpenTemplates}>
                <LayoutTemplate className="mr-1.5 h-3.5 w-3.5" />
                From Template
              </Button>
            )}
            <Button size="sm" onClick={() => setView({ mode: 'create' })}>
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              New Flow
            </Button>
          </div>
        }
      >
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
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="paused">Paused</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </div>

          <DataTable
            loading={loading}
            isEmpty={flows.length === 0}
            empty={
              <EmptyState
                icon={Workflow}
                title="No flows found"
                description="Create your first automation flow to get started."
                action={
                  <Button size="sm" onClick={() => setView({ mode: 'create' })}>
                    <Plus className="mr-1.5 h-3.5 w-3.5" />
                    New Flow
                  </Button>
                }
              />
            }
            pagination={{ page, totalPages, onPageChange: setPage }}
          >
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
                      {flow.status === 'active' ? (
                        <Badge variant="success">Active</Badge>
                      ) : flow.status === 'paused' ? (
                        <Badge variant="warning">Paused</Badge>
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
                          title="Edit"
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-7 w-7 p-0"
                          onClick={() => setView({ mode: 'edit', flow, tab: 'history' })}
                          title="Execution History"
                        >
                          <History className="h-3.5 w-3.5" />
                        </Button>
                        {flow.status === 'active' ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0"
                            onClick={() => void handleDeactivate(flow.id)}
                            disabled={deactivating === flow.id}
                            title="Pause"
                          >
                            <Pause className="h-3.5 w-3.5" />
                          </Button>
                        ) : (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0"
                            onClick={() => void handlePublish(flow.id)}
                            disabled={publishing === flow.id}
                            title="Publish"
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
          </DataTable>
      </PageSection>

      {/* Template picker dialog */}
      <Dialog open={showTemplates} onOpenChange={setShowTemplates}>
        <DialogContent className="max-w-2xl max-h-[80vh] flex flex-col">
          <DialogHeader>
            <DialogTitle>Start from a template</DialogTitle>
            <DialogDescription>Choose a pre-built automation flow.</DialogDescription>
          </DialogHeader>

          {/* Search + category filter */}
          <div className="flex items-center gap-3 pb-3">
            <div className="relative flex-1">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input
                className="pl-8 h-9"
                placeholder="Search templates..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
            <Select value={activeCategory} onValueChange={setActiveCategory}>
              <SelectTrigger className="w-44 h-9">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {categories.map((cat) => (
                  <SelectItem key={cat} value={cat}>{cat}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Template grid */}
          <div className="overflow-y-auto pr-1 -mr-1 flex-1 min-h-0">
            {filteredTemplates.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">No templates match your filters.</p>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {filteredTemplates.map((template) => {
                  const Icon = getTriggerIcon(template.trigger_type);
                  const steps = countSteps(template.steps);
                  const triggerLabel = formatLabel(
                    template.trigger_type.includes('.')
                      ? template.trigger_type.split('.').pop()!
                      : template.trigger_type
                  );

                  return (
                    <button
                      key={template.id}
                      type="button"
                      className="flex flex-col rounded-lg border border-border/50 p-3.5 text-left transition-colors hover:border-primary/30 hover:bg-accent/50"
                      onClick={() => handleUseTemplate(template)}
                    >
                      <div className="flex items-start gap-2.5">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
                          <Icon className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="min-w-0">
                          <span className="text-sm font-medium leading-tight line-clamp-1">{template.name}</span>
                          <span className="mt-0.5 block text-xs text-muted-foreground line-clamp-2">{template.description}</span>
                        </div>
                      </div>
                      <div className="mt-2.5 flex items-center gap-1.5">
                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0">{triggerLabel}</Badge>
                        <span className="text-[10px] text-muted-foreground">{steps} {steps === 1 ? 'step' : 'steps'}</span>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
