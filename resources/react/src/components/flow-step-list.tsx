import { useState, useEffect } from 'react';
import type { FlowNode, ActionNode, ConditionNode, DelayNode, JsonSchema, ActionDefinition, ListResponse } from '@/lib/api';
import { api } from '@/lib/api';
import { FlowStepEditor } from '@/components/flow-step-editor';
import { ConditionStepEditor } from '@/components/flow-editor/condition-step-editor';
import { DelayStepEditor } from '@/components/flow-editor/delay-step-editor';
import { StepTypePicker, type StepType } from '@/components/flow-editor/step-type-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowUp, ArrowDown, Trash2, Plus, GitBranch, Clock, Zap, ChevronDown, ChevronRight } from 'lucide-react';
import { generateNodeId } from '@/lib/utils';
import { getStepSummary, getStepStatus, type StepStatus } from '@/lib/flow-utils';

interface FlowStepListProps {
  steps: FlowNode[];
  onChange: (steps: FlowNode[]) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
  depth?: number;
  showTypePicker?: boolean;
  sampleData?: Record<string, unknown>;
}

const MAX_DEPTH = 3;

let cachedActionsForSummary: ActionDefinition[] | null = null;
let actionsFetchForSummary: Promise<ActionDefinition[]> | null = null;

export function createNode(type: StepType): FlowNode {
  const id = generateNodeId();
  switch (type) {
    case 'action':
      return { id, type: 'action', action: '', config: {} } satisfies ActionNode;
    case 'condition':
      return { id, type: 'condition', expression: '', then: [], else: [] } satisfies ConditionNode;
    case 'delay':
      return { id, type: 'delay', duration: 300, then: [] } satisfies DelayNode;
    default: {
      const _exhaustive: never = type;
      return _exhaustive;
    }
  }
}

const statusBadge: Record<StepStatus, { label: string; variant: 'secondary' | 'outline' | 'destructive' }> = {
  needs_setup: { label: 'needs setup', variant: 'secondary' },
  ready: { label: 'ready', variant: 'outline' },
  error: { label: 'error', variant: 'destructive' },
};

function StepIcon({ type }: { type: FlowNode['type'] }) {
  switch (type) {
    case 'action': return <Zap className="h-3.5 w-3.5" />;
    case 'condition': return <GitBranch className="h-3.5 w-3.5" />;
    case 'delay': return <Clock className="h-3.5 w-3.5" />;
  }
}

export function FlowStepList({ steps, onChange, payloadSchema, triggerType, depth = 0, showTypePicker = true, sampleData }: FlowStepListProps) {
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [actions, setActions] = useState<ActionDefinition[] | null>(cachedActionsForSummary);

  // Load actions for summary labels (deduplicate across nested instances)
  useEffect(() => {
    if (cachedActionsForSummary) return;
    const controller = new AbortController();

    if (!actionsFetchForSummary) {
      actionsFetchForSummary = api.get<ListResponse<ActionDefinition>>('actions', { signal: controller.signal })
        .then((res) => { cachedActionsForSummary = res.items; return res.items; })
        .catch((e) => { actionsFetchForSummary = null; throw e; });
    }

    actionsFetchForSummary
      .then((items) => { if (!controller.signal.aborted) { setActions(items); } })
      .catch(() => {});
    return () => { controller.abort(); };
  }, []);

  const addStep = (type: StepType) => {
    const node = createNode(type);
    onChange([...steps, node]);
    setExpandedId(node.id);
  };

  const addActionStep = () => addStep('action');

  const updateStep = (index: number, step: FlowNode) => {
    const next = [...steps];
    next[index] = step;
    onChange(next);
  };

  const removeStep = (index: number) => {
    const removed = steps[index];
    onChange(steps.filter((_, i) => i !== index));
    if (expandedId === removed.id) setExpandedId(null);
  };

  const moveStep = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= steps.length) return;
    const next = [...steps];
    [next[index], next[target]] = [next[target], next[index]];
    onChange(next);
  };

  const toggleExpand = (id: string) => {
    setExpandedId((prev) => (prev === id ? null : id));
  };

  // Auto-expand new steps and steps with needs_setup status
  useEffect(() => {
    if (steps.length === 0) return;
    const last = steps[steps.length - 1];
    if (getStepStatus(last) === 'needs_setup' && expandedId !== last.id) {
      setExpandedId(last.id);
    }
  }, [steps.length]); // eslint-disable-line react-hooks/exhaustive-deps

  const stepLabel = (step: FlowNode) => {
    switch (step.type) {
      case 'action': return 'Action';
      case 'condition': return 'Condition';
      case 'delay': return 'Delay';
    }
  };

  const atMaxDepth = depth >= MAX_DEPTH;

  return (
    <div className="space-y-3">
      {steps.length === 0 && (
        <p className="py-4 text-center text-sm text-muted-foreground">
          No steps yet. Add a step to get started.
        </p>
      )}

      {steps.map((step, i) => {
        const isExpanded = expandedId === step.id;
        const status = getStepStatus(step);
        const badge = statusBadge[status];
        const summary = getStepSummary(step, actions ?? undefined);

        return (
          <Card key={step.id} className="relative">
            <CardContent className="pt-4">
              {/* Header — always visible, clickable to toggle */}
              <div
                className="mb-0 flex items-center justify-between cursor-pointer select-none"
                onClick={() => toggleExpand(step.id)}
              >
                <div className="flex items-center gap-2 min-w-0">
                  {isExpanded
                    ? <ChevronDown className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                    : <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />}
                  <StepIcon type={step.type} />
                  <span className="text-xs font-medium text-muted-foreground shrink-0">
                    {stepLabel(step)}{depth === 0 ? ` ${i + 1}` : ''}
                  </span>
                  {!isExpanded && (
                    <span className="text-sm text-foreground truncate ml-1">
                      {summary}
                    </span>
                  )}
                  <Badge variant={badge.variant} className="ml-2 shrink-0 text-[10px] px-1.5 py-0">
                    {badge.label}
                  </Badge>
                </div>
                <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
                  <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => moveStep(i, -1)} disabled={i === 0}>
                    <ArrowUp className="h-3.5 w-3.5" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => moveStep(i, 1)} disabled={i === steps.length - 1}>
                    <ArrowDown className="h-3.5 w-3.5" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-7 w-7 p-0 text-destructive hover:text-destructive" onClick={() => removeStep(i)}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>

              {/* Expanded content */}
              {isExpanded && (
                <div className="mt-3 pt-3 border-t">
                  {step.type === 'action' && (
                    <FlowStepEditor
                      step={step}
                      stepIndex={i}
                      onChange={(updated) => updateStep(i, updated)}
                      payloadSchema={payloadSchema}
                      triggerType={triggerType}
                      sampleData={sampleData}
                    />
                  )}

                  {step.type === 'condition' && (
                    <ConditionStepEditor
                      step={step}
                      onChange={(updated) => updateStep(i, updated)}
                      payloadSchema={payloadSchema}
                      depth={depth}
                    />
                  )}

                  {step.type === 'delay' && (
                    <DelayStepEditor
                      step={step}
                      onChange={(updated) => updateStep(i, updated)}
                      payloadSchema={payloadSchema}
                      depth={depth}
                    />
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        );
      })}

      {!atMaxDepth && (
        showTypePicker ? (
          <StepTypePicker onSelect={addStep} />
        ) : (
          <Button variant="outline" size="sm" onClick={addActionStep} className="w-full">
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            Add Step
          </Button>
        )
      )}
    </div>
  );
}
