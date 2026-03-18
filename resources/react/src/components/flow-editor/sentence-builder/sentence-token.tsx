import { useState, useRef, useEffect, useMemo, forwardRef } from 'react';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Skeleton } from '@/components/ui/skeleton';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { api } from '@/lib/api';
import type { JsonSchema } from '@/lib/api';
import { cn } from '@/lib/utils';
import { findUnresolvedVariables } from '@/lib/template-preview';
import { buildMergedSchema } from '@/lib/flow-utils';
import { AlertTriangle } from 'lucide-react';

interface TokenOption {
  value: string;
  label: string;
  group?: string;
}

interface BaseTokenProps {
  value: string;
  placeholder?: string;
  className?: string;
  error?: boolean;
}

interface SelectTokenProps extends BaseTokenProps {
  mode: 'select';
  options: TokenOption[];
  onChange: (value: string) => void;
}

interface DynamicSelectTokenProps extends BaseTokenProps {
  mode: 'dynamic-select';
  optionsUrl: string;
  onChange: (value: string) => void;
}

interface TextTokenProps extends BaseTokenProps {
  mode: 'text';
  onChange: (value: string) => void;
  payloadSchema?: JsonSchema;
}

interface TextareaTokenProps extends BaseTokenProps {
  mode: 'textarea';
  onChange: (value: string) => void;
  payloadSchema?: JsonSchema;
}

interface NumberTokenProps extends BaseTokenProps {
  mode: 'number';
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  suffix?: string;
}

export type SentenceTokenProps =
  | SelectTokenProps
  | DynamicSelectTokenProps
  | TextTokenProps
  | TextareaTokenProps
  | NumberTokenProps;

const TokenBadge = forwardRef<
  HTMLButtonElement,
  {
    children: React.ReactNode;
    hasValue: boolean;
    error?: boolean;
    className?: string;
  } & React.ButtonHTMLAttributes<HTMLButtonElement>
>(function TokenBadge({ children, hasValue, error, className, ...rest }, ref) {
  return (
    <button
      ref={ref}
      type="button"
      {...rest}
      className={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-sm font-medium transition-colors',
        'border border-dashed cursor-pointer',
        hasValue
          ? 'border-border bg-muted/50 text-foreground hover:bg-muted'
          : 'border-muted-foreground/40 bg-transparent text-muted-foreground hover:bg-muted/30',
        error && 'border-destructive/60 bg-destructive/5 text-destructive',
        className,
      )}
    >
      {children}
    </button>
  );
});

function SelectContent({
  options,
  onSelect,
  onClose,
}: {
  options: TokenOption[];
  onSelect: (value: string) => void;
  onClose: () => void;
}) {
  // Group options
  const grouped = new Map<string, TokenOption[]>();
  for (const opt of options) {
    const group = opt.group ?? '';
    if (!grouped.has(group)) grouped.set(group, []);
    grouped.get(group)!.push(opt);
  }

  return (
    <div className="max-h-60 overflow-y-auto space-y-0.5">
      {[...grouped.entries()].map(([group, items]) => (
        <div key={group}>
          {group && (
            <p className="px-2 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground/60">
              {group}
            </p>
          )}
          {items.map((opt) => (
            <button
              key={opt.value}
              type="button"
              className="flex w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent transition-colors"
              onClick={() => { onSelect(opt.value); onClose(); }}
            >
              {opt.label}
            </button>
          ))}
        </div>
      ))}
    </div>
  );
}

function DynamicSelectContent({
  optionsUrl,
  onSelect,
  onClose,
}: {
  optionsUrl: string;
  onSelect: (value: string) => void;
  onClose: () => void;
}) {
  const [options, setOptions] = useState<TokenOption[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    api.get<{ options: TokenOption[] }>(optionsUrl, { signal: controller.signal })
      .then((res) => { setOptions(res.options); setLoading(false); })
      .catch((e) => { if (!(e instanceof DOMException && e.name === 'AbortError')) setLoading(false); });
    return () => { controller.abort(); };
  }, [optionsUrl]);

  if (loading) return <Skeleton className="h-20 w-full" />;

  return <SelectContent options={options} onSelect={onSelect} onClose={onClose} />;
}

function TextPopoverContent({
  value,
  onChange,
  onClose,
  payloadSchema,
  multiline,
}: {
  value: string;
  onChange: (v: string) => void;
  onClose: () => void;
  payloadSchema?: JsonSchema;
  multiline?: boolean;
}) {
  const ref = useRef<HTMLInputElement & HTMLTextAreaElement>(null);
  const [local, setLocal] = useState(value);

  useEffect(() => {
    ref.current?.focus();
  }, []);

  const handleInsertVariable = (variable: string) => {
    const el = ref.current;
    if (el) {
      const start = el.selectionStart ?? el.value.length;
      const end = el.selectionEnd ?? start;
      const next = local.slice(0, start) + variable + local.slice(end);
      setLocal(next);
      onChange(next);
      requestAnimationFrame(() => {
        el.focus();
        const pos = start + variable.length;
        el.setSelectionRange(pos, pos);
      });
    }
  };

  const handleChange = (v: string) => {
    setLocal(v);
    onChange(v);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !multiline) {
      e.preventDefault();
      onClose();
    }
    if (e.key === 'Escape') {
      onClose();
    }
  };

  return (
    <div className="flex items-center gap-1">
      {multiline ? (
        <textarea
          ref={ref as React.RefObject<HTMLTextAreaElement>}
          value={local}
          onChange={(e) => handleChange(e.target.value)}
          onKeyDown={handleKeyDown}
          rows={4}
          className="flex-1 rounded-md border bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-y"
        />
      ) : (
        <input
          ref={ref as React.RefObject<HTMLInputElement>}
          type="text"
          value={local}
          onChange={(e) => handleChange(e.target.value)}
          onKeyDown={handleKeyDown}
          className="flex-1 rounded-md border bg-background px-3 py-1.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        />
      )}
      <TemplateVariablePicker payloadSchema={payloadSchema} onInsert={handleInsertVariable} />
    </div>
  );
}

function UnresolvedVarWarning({ value, payloadSchema }: { value: string; payloadSchema?: JsonSchema }) {
  const unresolved = useMemo(() => {
    if (!value.includes('{{')) return [];
    return findUnresolvedVariables(value, buildMergedSchema(payloadSchema));
  }, [value, payloadSchema]);

  if (unresolved.length === 0) return null;

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <AlertTriangle className="ml-1 h-3 w-3 text-amber-500 shrink-0" />
      </TooltipTrigger>
      <TooltipContent side="top" className="max-w-xs">
        <p className="text-xs">
          Unknown variable{unresolved.length > 1 ? 's' : ''}:{' '}
          {unresolved.map((v) => `{{${v}}}`).join(', ')}
        </p>
      </TooltipContent>
    </Tooltip>
  );
}

function getDisplayLabel(value: string, options?: TokenOption[]): string {
  if (!options) return value;
  const opt = options.find((o) => o.value === value);
  return opt?.label ?? value;
}

export function SentenceToken(props: SentenceTokenProps) {
  const [open, setOpen] = useState(false);
  const hasValue = props.value !== '' && props.value != null;
  const displayValue = hasValue ? props.value : props.placeholder ?? '...';

  if (props.mode === 'select') {
    const label = hasValue ? getDisplayLabel(props.value, props.options) : displayValue;
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <TokenBadge hasValue={hasValue} error={props.error} className={props.className}>
            {label}
          </TokenBadge>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-2" align="start">
          <SelectContent
            options={props.options}
            onSelect={props.onChange}
            onClose={() => setOpen(false)}
          />
        </PopoverContent>
      </Popover>
    );
  }

  if (props.mode === 'dynamic-select') {
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <TokenBadge hasValue={hasValue} error={props.error} className={props.className}>
            {displayValue}
          </TokenBadge>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-2" align="start">
          <DynamicSelectContent
            optionsUrl={props.optionsUrl}
            onSelect={props.onChange}
            onClose={() => setOpen(false)}
          />
        </PopoverContent>
      </Popover>
    );
  }

  if (props.mode === 'text') {
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <TokenBadge hasValue={hasValue} error={props.error} className={props.className}>
            <span className="max-w-[200px] truncate">{displayValue}</span>
            <UnresolvedVarWarning value={props.value} payloadSchema={props.payloadSchema} />
          </TokenBadge>
        </PopoverTrigger>
        <PopoverContent className="w-80 p-3" align="start">
          <TextPopoverContent
            value={props.value}
            onChange={props.onChange}
            onClose={() => setOpen(false)}
            payloadSchema={props.payloadSchema}
          />
        </PopoverContent>
      </Popover>
    );
  }

  if (props.mode === 'textarea') {
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <TokenBadge hasValue={hasValue} error={props.error} className={props.className}>
            <span className="max-w-[300px] truncate">{displayValue}</span>
            <UnresolvedVarWarning value={props.value} payloadSchema={props.payloadSchema} />
          </TokenBadge>
        </PopoverTrigger>
        <PopoverContent className="w-96 p-3" align="start">
          <TextPopoverContent
            value={props.value}
            onChange={props.onChange}
            onClose={() => setOpen(false)}
            payloadSchema={props.payloadSchema}
            multiline
          />
        </PopoverContent>
      </Popover>
    );
  }

  if (props.mode === 'number') {
    const numValue = typeof props.value === 'string' ? Number(props.value) || 0 : Number(props.value);
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <TokenBadge hasValue={hasValue && numValue > 0} error={props.error} className={props.className}>
            {numValue > 0 ? `${numValue}${props.suffix ? ` ${props.suffix}` : ''}` : (props.placeholder ?? '...')}
          </TokenBadge>
        </PopoverTrigger>
        <PopoverContent className="w-48 p-3" align="start">
          <input
            type="number"
            value={numValue}
            min={props.min}
            max={props.max}
            onChange={(e) => props.onChange(Math.max(props.min ?? 0, Number(e.target.value) || 0))}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === 'Escape') setOpen(false); }}
            autoFocus
            className="w-full rounded-md border bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          />
        </PopoverContent>
      </Popover>
    );
  }

  return null;
}
