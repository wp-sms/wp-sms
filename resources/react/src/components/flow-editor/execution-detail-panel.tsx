import type { FlowExecution } from '@/lib/api';
import { groupStepLogs, computeDuration, type ProcessedStep } from '@/lib/execution-utils';
import { STEP_ICONS } from './sentence-builder/step-card';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { JsonViewer } from '@/components/ui/json-viewer';
import { ExecutionStatusBadge } from './execution-status-badge';
import { Layers, AlertCircle } from 'lucide-react';

interface ExecutionDetailPanelProps {
  execution: FlowExecution | null;
  onClose: () => void;
}

function StepStatusDot({ status }: { status: ProcessedStep['status'] }) {
  const colors: Record<ProcessedStep['status'], string> = {
    completed: 'bg-emerald-400',
    failed: 'bg-red-400',
    running: 'bg-blue-400',
    retrying: 'bg-amber-400',
  };
  return <span className={`inline-block h-2 w-2 rounded-full ${colors[status]}`} />;
}

function StepIcon({ type }: { type: string }) {
  const Icon = STEP_ICONS[type as keyof typeof STEP_ICONS] ?? Layers;
  return <Icon className="h-3.5 w-3.5" />;
}

function StepDetail({ step }: { step: ProcessedStep }) {
  return (
    <div className="border rounded-md p-3 space-y-2">
      <div className="flex items-center gap-2">
        <StepStatusDot status={step.status} />
        <StepIcon type={step.type} />
        <span className="font-mono text-xs text-muted-foreground">{step.nodeId}</span>
        <span className="text-xs text-muted-foreground capitalize">({step.type})</span>
        {step.duration && (
          <span className="ml-auto text-[10px] text-muted-foreground">{step.duration}</span>
        )}
        {step.status === 'running' && (
          <span className="ml-auto text-[10px] text-blue-600">In progress</span>
        )}
      </div>

      {step.input && <JsonViewer data={step.input} label="Input" />}
      {step.output && <JsonViewer data={step.output} label="Output" />}

      {step.error && (
        <div className="flex items-start gap-1.5 text-xs text-destructive">
          <AlertCircle className="h-3 w-3 mt-0.5 shrink-0" />
          <span>{step.error}</span>
        </div>
      )}

      {step.retries.length > 0 && (
        <div className="space-y-1">
          <p className="text-[10px] font-medium text-muted-foreground">Retries</p>
          {step.retries.map((r, i) => (
            <div key={i} className="text-[11px] text-amber-700">
              Attempt {r.attempt}/{r.maxAttempts}: {r.error}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export function ExecutionDetailPanel({ execution, onClose }: ExecutionDetailPanelProps) {
  const open = execution !== null;
  const steps = execution ? groupStepLogs(execution.step_logs) : [];

  return (
    <Drawer open={open} onOpenChange={(o) => !o && onClose()}>
      <DrawerContent className="sm:max-w-lg overflow-y-auto">
        {execution && (
          <>
            <DrawerHeader className="border-b">
              <div className="flex items-center justify-between">
                <DrawerTitle>Execution Details</DrawerTitle>
                <div className="flex items-center gap-2">
                  <ExecutionStatusBadge status={execution.status} />
                  {execution.completed_at && (
                    <span className="text-xs text-muted-foreground">
                      {computeDuration(execution.started_at, execution.completed_at)}
                    </span>
                  )}
                </div>
              </div>
            </DrawerHeader>

            <div className="p-4 space-y-5">
              {execution.error && (
                <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                  {execution.error}
                </div>
              )}

              <section>
                <h3 className="text-xs font-medium text-muted-foreground mb-2">Trigger Data</h3>
                <JsonViewer data={execution.trigger_data} defaultExpanded />
              </section>

              <section>
                <h3 className="text-xs font-medium text-muted-foreground mb-2">
                  Steps ({steps.length})
                </h3>
                {steps.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No steps executed yet</p>
                ) : (
                  <div className="space-y-2">
                    {steps.map((step) => (
                      <StepDetail key={step.nodeId} step={step} />
                    ))}
                  </div>
                )}
              </section>
            </div>
          </>
        )}
      </DrawerContent>
    </Drawer>
  );
}
