import type { ConditionNode, JsonSchema } from '@/lib/api';
import type { ConditionRule } from '@/lib/condition-utils';
import { rulesToExpression } from '@/lib/condition-utils';
import { ConditionBuilder } from './condition-builder';
import { FlowStepList } from '@/components/flow-step-list';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-react';

interface ConditionStepEditorProps {
  step: ConditionNode;
  onChange: (step: ConditionNode) => void;
  payloadSchema?: JsonSchema;
  depth: number;
}

export function ConditionStepEditor({ step, onChange, payloadSchema, depth }: ConditionStepEditorProps) {
  const rules: ConditionRule[] = step.rules ?? [];

  const handleRulesChange = (newRules: ConditionRule[]) => {
    onChange({
      ...step,
      rules: newRules,
      expression: rulesToExpression(newRules),
    });
  };

  return (
    <div className="space-y-4">
      {/* Condition builder */}
      <ConditionBuilder
        rules={rules}
        onChange={handleRulesChange}
        payloadSchema={payloadSchema}
      />

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
