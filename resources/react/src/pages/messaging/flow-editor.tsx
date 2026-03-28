import { __ } from '@wordpress/i18n';
import { useState, useCallback, useRef } from 'react';
import type { Flow, FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { SentenceBuilder } from '@/components/flow-editor/sentence-builder/sentence-builder';
import { ExecutionHistory } from '@/components/flow-editor/execution-history';
import { getCachedActions } from '@/hooks/use-actions';
import { validateFlowSteps, flattenSteps, nestSteps } from '@/lib/flow-utils';
import { ArrowLeft, Save, Rocket, Pause } from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';

interface FlowEditorProps {
  flow?: Flow;
  initialData?: Partial<Flow>;
  onSave: (data: Partial<Flow>) => Promise<Flow>;
  onPublish?: (id: string) => Promise<Flow>;
  onDeactivate?: (id: string) => Promise<Flow>;
  onBack: () => void;
  defaultTab?: string;
}

export function FlowEditor({ flow, initialData, onSave, onPublish, onDeactivate, onBack, defaultTab }: FlowEditorProps) {
  const source = flow ?? initialData;
  const [name, setName] = useState(source?.name ?? '');
  const [description, setDescription] = useState(source?.description ?? '');
  const [triggerType, setTriggerType] = useState(source?.trigger_type ?? '');
  const [triggerConfig, setTriggerConfig] = useState<Record<string, unknown>>(source?.trigger_config ?? {});
  const [steps, setSteps] = useState<FlowNode[]>(() => flattenSteps(source?.steps ?? []));
  const [saving, setSaving] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [deactivating, setDeactivating] = useState(false);
  const [payloadSchema, setPayloadSchema] = useState<JsonSchema | undefined>();
  const [sampleData, setSampleData] = useState<Record<string, unknown> | undefined>();
  const confirm = useConfirm();

  const isEdit = !!flow;

  // Refs for stable dirty detection — avoids re-creating isDirty on every keystroke
  const stateRef = useRef({ name: source?.name ?? '', description: source?.description ?? '' });
  stateRef.current = { name, description };
  const initialRef = useRef({ name: source?.name ?? '', description: source?.description ?? '' });
  const isDirty = useCallback(() => {
    return stateRef.current.name !== initialRef.current.name
      || stateRef.current.description !== initialRef.current.description;
  }, []);

  const { guardedBack } = useUnsavedChanges({ isDirty, onLeave: onBack });

  const handleChangeTrigger = useCallback((type: string, config: Record<string, unknown>) => {
    setTriggerType(type);
    setTriggerConfig(config);
    setSampleData(undefined);
  }, []);


  const handleSave = async () => {
    if (!name.trim()) {
      toast.error(__('Flow name is required.', 'wp-sms'));
      return;
    }
    if (!triggerType) {
      toast.error(__('Please select a trigger.', 'wp-sms'));
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
        steps: nestSteps(steps),
      });
      toast.success(isEdit ? __('Flow updated.', 'wp-sms') : __('Flow created.', 'wp-sms'));
      onBack();
    } catch {
      toast.error(__('Failed to save flow.', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  };

  const handlePublish = async () => {
    if (!flow?.id || !onPublish) return;
    const ok = await confirm({
      title: 'Publish flow?',
      description: 'This flow will become active and start processing events immediately.',
      confirmLabel: 'Publish',
    });
    if (!ok) return;
    setPublishing(true);
    try {
      await onPublish(flow.id);
      toast.success(__('Flow published.', 'wp-sms'));
      onBack();
    } catch {
      toast.error(__('Failed to publish flow.', 'wp-sms'));
    } finally {
      setPublishing(false);
    }
  };

  const handleDeactivate = async () => {
    if (!flow?.id || !onDeactivate) return;
    setDeactivating(true);
    try {
      await onDeactivate(flow.id);
      toast.success(__('Flow deactivated.', 'wp-sms'));
      onBack();
    } catch {
      toast.error(__('Failed to deactivate flow.', 'wp-sms'));
    } finally {
      setDeactivating(false);
    }
  };

  const editorContent = (
    <>
      {/* Details card */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{__('Details', 'wp-sms')}</CardTitle>
          <CardDescription>{__('Name and describe this automation flow.', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Field>
            <FieldLabel htmlFor="flow-name">Name *</FieldLabel>
            <Input
              id="flow-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={__('e.g. Welcome new contacts', 'wp-sms')}
            />
          </Field>
          <Field>
            <FieldLabel htmlFor="flow-description">Description</FieldLabel>
            <Input
              id="flow-description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder={__('Optional description', 'wp-sms')}
            />
          </Field>
        </CardContent>
      </Card>

      {/* Sentence Builder */}
      <SentenceBuilder
        triggerType={triggerType}
        triggerConfig={triggerConfig}
        steps={steps}
        onChangeTrigger={handleChangeTrigger}
        onChangeSteps={setSteps}
        onPayloadSchemaChange={setPayloadSchema}
        payloadSchema={payloadSchema}
        flowId={flow?.id}
        sampleData={sampleData}
        onSampleDataChange={setSampleData}
      />

      {/* Actions */}
      <div className="flex items-center gap-2 justify-end">
        {isEdit && flow.status === 'active' && onDeactivate && (
          <Button
            variant="outline"
            onClick={handleDeactivate}
            disabled={deactivating || saving}
          >
            <Pause className="me-1.5 h-4 w-4" />
            {deactivating ? 'Deactivating...' : 'Deactivate'}
          </Button>
        )}
        {isEdit && flow.status !== 'active' && onPublish && (
          <Button
            variant="outline"
            onClick={handlePublish}
            disabled={publishing || saving}
          >
            <Rocket className="me-1.5 h-4 w-4" />
            {publishing ? 'Publishing...' : 'Publish'}
          </Button>
        )}
        <Button onClick={handleSave} disabled={saving || publishing || deactivating}>
          <Save className="me-1.5 h-4 w-4" />
          {saving ? 'Saving...' : isEdit ? 'Update Flow' : 'Create Flow'}
        </Button>
      </div>
    </>
  );

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={() => void guardedBack()}>
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
        <h2 className="text-base font-semibold">{isEdit ? 'Edit Flow' : 'Create Flow'}</h2>
      </div>

      {isEdit ? (
        <Tabs defaultValue={defaultTab ?? 'editor'}>
          <TabsList>
            <TabsTrigger value="editor">{__('Editor', 'wp-sms')}</TabsTrigger>
            <TabsTrigger value="history">{__('Execution History', 'wp-sms')}</TabsTrigger>
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
