import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import type { JsonSchema } from '@/lib/api';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Zap, ChevronDown, ChevronRight } from 'lucide-react';
import { extractExampleData, formatSampleData } from '@/lib/template-preview';

interface UseTestTriggerOptions {
  flowId?: string;
  triggerType: string;
  payloadSchema?: JsonSchema;
  onSampleDataChange?: (data: Record<string, unknown> | undefined) => void;
}

export function useTestTrigger({ flowId, triggerType, payloadSchema, onSampleDataChange }: UseTestTriggerOptions) {
  const [testing, setTesting] = useState(false);

  const handleTest = async () => {
    if (!flowId || !triggerType) return;
    setTesting(true);
    try {
      const res = await api.post<{ success: boolean; data: Record<string, unknown> }>(`flows/${flowId}/test-trigger`, {});
      onSampleDataChange?.(res.data);
    } catch {
      if (payloadSchema) {
        const examples = extractExampleData(payloadSchema);
        if (Object.keys(examples).length > 0) {
          onSampleDataChange?.(examples);
        }
      }
    } finally {
      setTesting(false);
    }
  };

  return { testing, handleTest };
}

interface TestTriggerButtonProps {
  triggerType: string;
  flowId?: string;
  testing: boolean;
  onTest: () => void;
}

export function TestTriggerButton({ triggerType, flowId, testing, onTest }: TestTriggerButtonProps) {
  if (!triggerType || !flowId) return null;
  return (
    <Button variant="outline" size="sm" onClick={onTest} disabled={testing} className="h-7 text-xs">
      <Zap className="me-1 h-3 w-3" />
      {testing ? __('Testing...', 'wp-sms') : __('Test trigger', 'wp-sms')}
    </Button>
  );
}

interface SampleDataPreviewProps {
  sampleData?: Record<string, unknown>;
}

export function SampleDataPreview({ sampleData }: SampleDataPreviewProps) {
  const [show, setShow] = useState(false);

  if (!sampleData || Object.keys(sampleData).length === 0) return null;

  return (
    <div className="mt-3">
      <button
        type="button"
        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
        onClick={() => setShow(!show)}
      >
        {show ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3 rtl:scale-x-[-1]" />}
        {__('Sample data', 'wp-sms')}
      </button>
      {show && (
        <pre className="mt-1 max-h-40 overflow-auto rounded bg-muted/50 p-2 text-[11px] leading-relaxed text-muted-foreground">
          {formatSampleData(sampleData)}
        </pre>
      )}
    </div>
  );
}
