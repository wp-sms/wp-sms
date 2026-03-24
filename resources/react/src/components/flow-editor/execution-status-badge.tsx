import type { FlowExecution } from '@/lib/api';
import { EXECUTION_STATUS_VARIANTS } from '@/lib/execution-utils';
import { Badge } from '@/components/ui/badge';

export function ExecutionStatusBadge({ status }: { status: FlowExecution['status'] }) {
  const v = EXECUTION_STATUS_VARIANTS[status] ?? { variant: 'outline' as const, label: status };
  return <Badge variant={v.variant}>{v.label}</Badge>;
}
