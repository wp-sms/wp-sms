import { useRef } from 'react';
import type { ConditionNode, JsonSchema } from '@/lib/api';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { FlowStepList } from '@/components/flow-step-list';
import { TemplateVariablePicker } from './template-variable-picker';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-react';

interface ConditionStepEditorProps {
  step: ConditionNode;
  onChange: (step: ConditionNode) => void;
  payloadSchema?: JsonSchema;
  depth: number;
}

export function ConditionStepEditor({ step, onChange, payloadSchema, depth }: ConditionStepEditorProps) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const insertAtCursor = (variable: string) => {
    const el = textareaRef.current;
    if (el) {
      const start = el.selectionStart ?? el.value.length;
      const end = el.selectionEnd ?? start;
      const expr = step.expression.slice(0, start) + variable + step.expression.slice(end);
      onChange({ ...step, expression: expr });
      requestAnimationFrame(() => {
        el.focus();
        const pos = start + variable.length;
        el.setSelectionRange(pos, pos);
      });
    } else {
      onChange({ ...step, expression: step.expression + variable });
    }
  };

  return (
    <div className="space-y-4">
      {/* Expression */}
      <Field>
        <div className="flex items-center justify-between">
          <FieldLabel htmlFor={`condition-expr-${step.id}`}>Expression</FieldLabel>
          {payloadSchema && (
            <TemplateVariablePicker
              payloadSchema={payloadSchema}
              onInsert={insertAtCursor}
            />
          )}
        </div>
        <Textarea
          ref={textareaRef}
          id={`condition-expr-${step.id}`}
          className="font-mono text-sm"
          placeholder='e.g. {{user.role}} == "admin"'
          value={step.expression}
          onChange={(e) => onChange({ ...step, expression: e.target.value })}
          rows={2}
        />
        <FieldDescription>
          A boolean expression evaluated against the trigger payload.
        </FieldDescription>
      </Field>

      {/* Then branch */}
      <div className="space-y-2">
        <p className="text-sm font-medium text-emerald-600">Then (if true)</p>
        <div className="border-l-2 border-emerald-200 pl-4">
          <FlowStepList
            steps={step.then}
            onChange={(then) => onChange({ ...step, then })}
            payloadSchema={payloadSchema}
            depth={depth + 1}
          />
        </div>
      </div>

      {/* Else branch */}
      <Collapsible>
        <CollapsibleTrigger className="flex items-center gap-1 text-sm font-medium text-orange-600 hover:underline">
          <ChevronDown className="h-3.5 w-3.5" />
          Else (if false)
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="mt-2 border-l-2 border-orange-200 pl-4">
            <FlowStepList
              steps={step.else}
              onChange={(elseSteps) => onChange({ ...step, else: elseSteps })}
              payloadSchema={payloadSchema}
              depth={depth + 1}
            />
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  );
}
