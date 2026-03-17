import { useState, useEffect } from 'react';
import type { FlowNode, ActionNode, ConditionNode, DelayNode, JsonSchema, ActionDefinition, ListResponse } from '@/lib/api';
import { api } from '@/lib/api';
import { FlowStepEditor } from '@/components/flow-step-editor';
import { ConditionStepEditor } from '@/components/flow-editor/condition-step-editor';
import { DelayStepEditor } from '@/components/flow-editor/delay-step-editor';
import { StepTypePicker, StepTypePopover, STEP_TYPES, type StepType } from '@/components/flow-editor/step-type-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip';
import { ArrowUp, ArrowDown, Trash2, Plus, GitBranch, Clock, Zap, ChevronDown, ChevronRight } from 'lucide-react';
import { generateNodeId, cn } from '@/lib/utils';
import { getStepSummary, getStepStatus, STEP_COLORS, STATUS_DOT, type StepStatus } from '@/lib/flow-utils';

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

function StepIcon({ type }: { type: FlowNode['type'] }) {
  const colors = STEP_COLORS[type];
  const iconClass = cn('h-3.5 w-3.5', colors.iconFg);
  return (
    <div className={cn('flex h-7 w-7 items-center justify-center rounded-full shrink-0', colors.iconBg)}>
      {type === 'action' && <Zap className={iconClass} />}
      {type === 'condition' && <GitBranch className={iconClass} />}
      {type === 'delay' && <Clock className={iconClass} />}
    </div>
  );
}

function StatusDot({ status }: { status: StepStatus }) {
  const dot = STATUS_DOT[status];
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className={cn('inline-block h-2 w-2 rounded-full shrink-0', dot.color)} />
      </TooltipTrigger>
      <TooltipContent side="top">{dot.label}</TooltipContent>
    </Tooltip>
  );
}

function EmptyState() {
  return (
    <div className="rounded-lg border-2 border-dashed border-border py-8 px-4 text-center">
      <div className="flex items-center justify-center gap-3 mb-3">
        {STEP_TYPES.map(({ type, icon: Icon }) => {
          const colors = STEP_COLORS[type];
          return (
            <div key={type} className={cn('flex h-8 w-8 items-center justify-center rounded-full', colors.iconBg)}>
              <Icon className={cn('h-4 w-4', colors.iconFg)} />
            </div>
          );
        })}
      </div>
      <p className="text-sm font-medium text-foreground">No steps yet</p>
      <p className="text-xs text-muted-foreground mt-1">Add an action, condition, or delay to get started.</p>
    </div>
  );
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

  const addStep = (type: StepType, atIndex?: number) => {
    const node = createNode(type);
    if (atIndex !== undefined) {
      const next = [...steps];
      next.splice(atIndex, 0, node);
      onChange(next);
    } else {
      onChange([...steps, node]);
    }
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
    <div className="space-y-0">
      {steps.length === 0 && depth === 0 && <EmptyState />}
      {steps.length === 0 && depth > 0 && (
        <p className="py-4 text-center text-sm text-muted-foreground">
          No steps yet. Add a step to get started.
        </p>
      )}

      {steps.map((step, i) => {
        const isExpanded = expandedId === step.id;
        const status = getStepStatus(step);
        const summary = getStepSummary(step, actions ?? undefined);
        const colors = STEP_COLORS[step.type];

        return (
          <div key={step.id}>
            {/* Connector + inline add between steps */}
            {i > 0 && !atMaxDepth && (
              <div className="flex flex-col items-center py-1">
                <div className="h-2 w-px bg-border" />
                <StepTypePopover onSelect={(type) => addStep(type, i)} />
                <div className="h-2 w-px bg-border" />
              </div>
            )}
            {i > 0 && atMaxDepth && <div className="h-3" />}

            <Card className={cn('relative border-l-[3px]', colors.border)}>
              <CardContent className="pt-4">
                {/* Header — always visible, clickable to toggle */}
                <div
                  className="flex items-start justify-between cursor-pointer select-none"
                  onClick={() => toggleExpand(step.id)}
                >
                  <div className="flex items-start gap-2 min-w-0 flex-1">
                    {isExpanded
                      ? <ChevronDown className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                      : <ChevronRight className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />}
                    <StepIcon type={step.type} />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">
                          {stepLabel(step)}{depth === 0 ? ` ${i + 1}` : ''}
                        </span>
                        <StatusDot status={status} />
                      </div>
                      {!isExpanded && (
                        <p className="text-xs text-muted-foreground truncate mt-0.5">
                          {summary}
                        </p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-0.5 shrink-0 ml-2" onClick={(e) => e.stopPropagation()}>
                    <Button variant="ghost" size="icon-xs" onClick={() => moveStep(i, -1)} disabled={i === 0}>
                      <ArrowUp className="h-3 w-3" />
                    </Button>
                    <Button variant="ghost" size="icon-xs" onClick={() => moveStep(i, 1)} disabled={i === steps.length - 1}>
                      <ArrowDown className="h-3 w-3" />
                    </Button>
                    <Button variant="ghost" size="icon-xs" className="text-destructive hover:text-destructive" onClick={() => removeStep(i)}>
                      <Trash2 className="h-3 w-3" />
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
          </div>
        );
      })}

      {!atMaxDepth && (
        <div className="pt-3">
          {showTypePicker ? (
            <StepTypePicker onSelect={(type) => addStep(type)} />
          ) : (
            <Button variant="outline" size="sm" onClick={addActionStep} className="w-full">
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Add Step
            </Button>
          )}
        </div>
      )}
    </div>
  );
}
