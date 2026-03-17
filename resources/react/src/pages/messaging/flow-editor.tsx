import { useState, useCallback } from 'react';
import type { Flow, FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { SimpleMode } from '@/components/flow-editor/simple-mode';
import { AdvancedMode } from '@/components/flow-editor/advanced-mode';
import { ExecutionHistory } from '@/components/flow-editor/execution-history';
import { getCachedActions } from '@/components/flow-step-editor';
import { validateFlowSteps } from '@/lib/flow-utils';
import { ArrowLeft, Save, Rocket, Pause } from 'lucide-react';
import { toast } from 'sonner';

interface FlowEditorProps {
  flow?: Flow;
  initialData?: Partial<Flow>;
  onSave: (data: Partial<Flow>) => Promise<Flow>;
  onPublish?: (id: string) => Promise<Flow>;
  onDeactivate?: (id: string) => Promise<Flow>;
  onBack: () => void;
  defaultTab?: string;
}

type EditorMode = 'simple' | 'advanced';

function detectMode(steps: FlowNode[]): EditorMode {
  const actionSteps = steps.filter((s) => s.type === 'action');
  const hasNonAction = steps.some((s) => s.type !== 'action');
  if (hasNonAction || actionSteps.length > 1) return 'advanced';
  return 'simple';
}

export function FlowEditor({ flow, initialData, onSave, onPublish, onDeactivate, onBack, defaultTab }: FlowEditorProps) {
  const source = flow ?? initialData;
  const [name, setName] = useState(source?.name ?? '');
  const [description, setDescription] = useState(source?.description ?? '');
  const [triggerType, setTriggerType] = useState(source?.trigger_type ?? '');
  const [triggerConfig, setTriggerConfig] = useState<Record<string, unknown>>(source?.trigger_config ?? {});
  const [steps, setSteps] = useState<FlowNode[]>(source?.steps ?? []);
  const [saving, setSaving] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [deactivating, setDeactivating] = useState(false);
  const [mode, setMode] = useState<EditorMode>(detectMode(source?.steps ?? []));
  const [payloadSchema, setPayloadSchema] = useState<JsonSchema | undefined>();
  const [sampleData, setSampleData] = useState<Record<string, unknown> | undefined>();

  const isEdit = !!flow;

  const handleChangeTrigger = useCallback((type: string, config: Record<string, unknown>) => {
    setTriggerType(type);
    setTriggerConfig(config);
    setSampleData(undefined); // Reset sample data when trigger changes
  }, []);

  const handlePayloadSchemaChange = useCallback((schema: JsonSchema | undefined) => {
    setPayloadSchema(schema);
  }, []);

  const handleSampleDataChange = useCallback((data: Record<string, unknown> | undefined) => {
    setSampleData(data);
  }, []);

  const handleSave = async () => {
    if (!name.trim()) {
      toast.error('Flow name is required.');
      return;
    }
    if (!triggerType) {
      toast.error('Please select a trigger.');
      return;
    }

    const { valid, errors } = validateFlowSteps(steps, getCachedActions());
    if (!valid) {
      errors.forEach((err) => toast.error(err));
      return;
    }

    setSaving(true);
    try {
      await onSave({
        name: name.trim(),
        description: description.trim() || null,
        trigger_type: triggerType,
        trigger_config: triggerConfig,
        steps,
      });
      toast.success(isEdit ? 'Flow updated.' : 'Flow created.');
      onBack();
    } catch {
      toast.error('Failed to save flow.');
    } finally {
      setSaving(false);
    }
  };

  const handlePublish = async () => {
    if (!flow?.id || !onPublish) return;
    setPublishing(true);
    try {
      await onPublish(flow.id);
      toast.success('Flow published.');
      onBack();
    } catch {
      toast.error('Failed to publish flow.');
    } finally {
      setPublishing(false);
    }
  };

  const handleDeactivate = async () => {
    if (!flow?.id || !onDeactivate) return;
    setDeactivating(true);
    try {
      await onDeactivate(flow.id);
      toast.success('Flow deactivated.');
      onBack();
    } catch {
      toast.error('Failed to deactivate flow.');
    } finally {
      setDeactivating(false);
    }
  };

  const editorContent = (
    <>
      {/* Details card */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Details</CardTitle>
          <CardDescription>Name and describe this automation flow.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Field>
            <FieldLabel htmlFor="flow-name">Name *</FieldLabel>
            <Input
              id="flow-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Welcome new contacts"
            />
          </Field>
          <Field>
            <FieldLabel htmlFor="flow-description">Description</FieldLabel>
            <Input
              id="flow-description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Optional description"
            />
          </Field>
        </CardContent>
      </Card>

      {/* Mode-dependent editor */}
      {mode === 'simple' ? (
        <SimpleMode
          triggerType={triggerType}
          triggerConfig={triggerConfig}
          steps={steps}
          onChangeTrigger={handleChangeTrigger}
          onChangeSteps={setSteps}
          onPayloadSchemaChange={handlePayloadSchemaChange}
          payloadSchema={payloadSchema}
          onSwitchToAdvanced={() => setMode('advanced')}
          flowId={flow?.id}
          sampleData={sampleData}
          onSampleDataChange={handleSampleDataChange}
        />
      ) : (
        <AdvancedMode
          triggerType={triggerType}
          triggerConfig={triggerConfig}
          steps={steps}
          onChangeTrigger={handleChangeTrigger}
          onChangeSteps={setSteps}
          onPayloadSchemaChange={handlePayloadSchemaChange}
          payloadSchema={payloadSchema}
          flowId={flow?.id}
          sampleData={sampleData}
          onSampleDataChange={handleSampleDataChange}
        />
      )}

      {/* Actions */}
      <div className="flex items-center gap-2 justify-end">
        {isEdit && flow.status === 'active' && onDeactivate && (
          <Button
            variant="outline"
            onClick={handleDeactivate}
            disabled={deactivating || saving}
          >
            <Pause className="mr-1.5 h-4 w-4" />
            {deactivating ? 'Deactivating...' : 'Deactivate'}
          </Button>
        )}
        {isEdit && flow.status !== 'active' && onPublish && (
          <Button
            variant="outline"
            onClick={handlePublish}
            disabled={publishing || saving}
          >
            <Rocket className="mr-1.5 h-4 w-4" />
            {publishing ? 'Publishing...' : 'Publish'}
          </Button>
        )}
        <Button onClick={handleSave} disabled={saving || publishing || deactivating}>
          <Save className="mr-1.5 h-4 w-4" />
          {saving ? 'Saving...' : isEdit ? 'Update Flow' : 'Create Flow'}
        </Button>
      </div>
    </>
  );

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="mr-1 h-4 w-4" />
          Back
        </Button>
        <h2 className="text-base font-semibold">{isEdit ? 'Edit Flow' : 'Create Flow'}</h2>
        {mode === 'advanced' && (
          <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Advanced</span>
        )}
      </div>

      {isEdit ? (
        <Tabs defaultValue={defaultTab ?? 'editor'}>
          <TabsList>
            <TabsTrigger value="editor">Editor</TabsTrigger>
            <TabsTrigger value="history">Execution History</TabsTrigger>
          </TabsList>
          <TabsContent value="editor" className="space-y-4 mt-4">
            {editorContent}
          </TabsContent>
          <TabsContent value="history" className="mt-4">
            <ExecutionHistory flowId={flow.id} />
          </TabsContent>
        </Tabs>
      ) : (
        <div className="space-y-4">
          {editorContent}
        </div>
      )}
    </div>
  );
}
