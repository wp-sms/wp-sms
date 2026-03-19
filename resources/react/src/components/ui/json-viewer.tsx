import { useState, useMemo } from 'react';
import { ChevronRight, ChevronDown } from 'lucide-react';

interface JsonViewerProps {
  data: Record<string, unknown>;
  label?: string;
  defaultExpanded?: boolean;
}

export function JsonViewer({ data, label, defaultExpanded = false }: JsonViewerProps) {
  const [expanded, setExpanded] = useState(defaultExpanded);
  const formatted = useMemo(() => JSON.stringify(data, null, 2), [data]);
  const keyCount = useMemo(() => (data ? Object.keys(data).length : 0), [data]);

  if (!data || keyCount === 0) return null;

  return (
    <div className="text-xs">
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
      >
        {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
        {label && <span className="font-medium">{label}</span>}
        {!expanded && <span className="text-[10px] text-muted-foreground/70">{keyCount} keys</span>}
      </button>
      {expanded && (
        <pre className="mt-1 rounded border bg-muted/30 p-2 text-[11px] font-mono max-h-60 overflow-auto whitespace-pre-wrap break-all">
          {formatted}
        </pre>
      )}
    </div>
  );
}
