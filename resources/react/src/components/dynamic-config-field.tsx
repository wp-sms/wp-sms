import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { ChevronsUpDown, Loader2, AlertCircle, RefreshCw } from 'lucide-react';
import { api, type GatewayConfigField, type GatewayConfig } from '@/lib/api';
import { isAbortError, getErrorMessage } from '@/lib/error-utils';

interface DynamicOption {
  value: string;
  label: string;
}

interface CacheEntry {
  options: DynamicOption[];
  ts: number;
}

const CACHE_TTL = 30_000;
const MAX_CACHE_SIZE = 50;
const DEBOUNCE_MS = 600;
const cache = new Map<string, CacheEntry>();

function evictStaleCache() {
  const now = Date.now();
  for (const [key, entry] of cache) {
    if (now - entry.ts > CACHE_TTL) cache.delete(key);
  }
  // If still over limit, drop oldest
  if (cache.size > MAX_CACHE_SIZE) {
    const keys = [...cache.keys()];
    for (let i = 0; i < keys.length - MAX_CACHE_SIZE; i++) {
      cache.delete(keys[i]);
    }
  }
}

interface DynamicConfigFieldProps {
  fieldKey: string;
  field: GatewayConfigField;
  value: unknown;
  onChange: (key: string, value: unknown) => void;
  gatewayId: string;
  section: string;
  draftConfig: GatewayConfig;
  sharedSchema: Record<string, GatewayConfigField>;
}

export function DynamicConfigField({
  fieldKey,
  field,
  value,
  onChange,
  gatewayId,
  section,
  draftConfig,
  sharedSchema,
}: DynamicConfigFieldProps) {
  const [options, setOptions] = useState<DynamicOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const abortRef = useRef<AbortController>();
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  const id = `gateway-${fieldKey}`;
  const currentValue = String(value ?? '');

  // Check if required shared credentials are filled in draft
  const credentialsReady = useMemo(() => {
    const shared = draftConfig.shared ?? {};
    for (const [key, schema] of Object.entries(sharedSchema)) {
      if (schema.required && !shared[key]) return false;
    }
    return true;
  }, [draftConfig.shared, sharedSchema]);

  // Stable serialization of shared config for change detection
  const credentialsKey = useMemo(
    () => JSON.stringify(draftConfig.shared ?? {}),
    [draftConfig.shared],
  );

  // Resolve depends_on values once — used for both cache key and API request
  const resolvedContext = useMemo(() => {
    if (!field.depends_on?.length) return null;
    const ctx: Record<string, unknown> = {};
    for (const dep of field.depends_on) {
      const val = draftConfig.shared?.[dep] ?? draftConfig.channels?.[section]?.[dep];
      if (val != null) ctx[dep] = val;
    }
    return ctx;
  }, [field.depends_on, draftConfig.shared, draftConfig.channels, section]);

  const contextKey = useMemo(
    () => resolvedContext ? JSON.stringify(resolvedContext) : '',
    [resolvedContext],
  );

  const fetchOptions = useCallback(() => {
    if (!credentialsReady) return;

    const cacheKey = `${gatewayId}/${section}/${fieldKey}/${credentialsKey}/${contextKey}`;
    const cached = cache.get(cacheKey);
    if (cached && Date.now() - cached.ts < CACHE_TTL) {
      setOptions(cached.options);
      setError(null);
      setLoading(false);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);

    api.post<{ options: DynamicOption[] }>(
      `gateways/${gatewayId}/config-options/${section}/${fieldKey}`,
      { config: draftConfig, context: resolvedContext ?? {} },
      { signal: controller.signal },
    )
      .then((res) => {
        if (!controller.signal.aborted) {
          setOptions(res.options);
          evictStaleCache();
          cache.set(cacheKey, { options: res.options, ts: Date.now() });
          setLoading(false);
        }
      })
      .catch((e) => {
        if (isAbortError(e)) return;
        setError(getErrorMessage(e, 'Failed to load options'));
        setLoading(false);
      });
  }, [credentialsReady, credentialsKey, contextKey, gatewayId, section, fieldKey, draftConfig, resolvedContext]);

  // Debounced fetch when credentials or dependencies change
  useEffect(() => {
    if (!credentialsReady) {
      setOptions([]);
      setError(null);
      return;
    }

    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(fetchOptions, DEBOUNCE_MS);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [credentialsReady, fetchOptions]);

  // Filter options by search term
  const filtered = useMemo(() => {
    if (!search) return options;
    const q = search.toLowerCase();
    return options.filter(
      (o) => o.label.toLowerCase().includes(q) || o.value.toLowerCase().includes(q),
    );
  }, [options, search]);

  // Find label for current value
  const selectedLabel = useMemo(
    () => options.find((o) => o.value === currentValue)?.label,
    [options, currentValue],
  );

  // Clear search when popover closes
  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen);
    if (!nextOpen) setSearch('');
  }

  if (!credentialsReady) {
    return (
      <Field>
        <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
        <Input
          id={id}
          type="text"
          dir="ltr"
          value={currentValue}
          onChange={(e) => onChange(fieldKey, e.target.value)}
          placeholder={field.placeholder}
          disabled
        />
        <p className="text-xs text-muted-foreground">{__('Enter credentials above to load options', 'wp-sms')}</p>
      </Field>
    );
  }

  return (
    <Field>
      <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
      <Popover open={open} onOpenChange={handleOpenChange}>
        <div className="flex gap-1.5">
          <Input
            id={id}
            type="text"
            dir="ltr"
            value={currentValue}
            onChange={(e) => onChange(fieldKey, e.target.value)}
            placeholder={field.placeholder}
            className="flex-1"
          />
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="shrink-0"
              disabled={loading && options.length === 0}
            >
              {loading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <ChevronsUpDown className="h-4 w-4" />
              )}
            </Button>
          </PopoverTrigger>
        </div>

        <PopoverContent
          className="w-[--radix-popover-trigger-width] p-0"
          align="start"
          onOpenAutoFocus={(e) => e.preventDefault()}
        >
          <div className="p-2 border-b">
            <Input
              placeholder={__('Search...', 'wp-sms')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-8 text-sm"
              autoFocus
            />
          </div>

          <div className="max-h-48 overflow-y-auto p-1">
            {loading && options.length === 0 ? (
              <div className="space-y-1.5 p-2">
                <Skeleton className="h-6 w-full" />
                <Skeleton className="h-6 w-3/4" />
                <Skeleton className="h-6 w-5/6" />
              </div>
            ) : error ? (
              <div className="p-3 text-center space-y-2">
                <p className="text-sm text-destructive flex items-center justify-center gap-1.5">
                  <AlertCircle className="h-3.5 w-3.5" />
                  {error}
                </p>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={fetchOptions}
                >
                  <RefreshCw className="h-3.5 w-3.5" />
                  {__('Retry', 'wp-sms')}
                </Button>
              </div>
            ) : filtered.length === 0 ? (
              <p className="p-3 text-sm text-muted-foreground text-center">
                {options.length === 0 ? __('No options found on your account', 'wp-sms') : __('No matching results', 'wp-sms')}
              </p>
            ) : (
              filtered.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  className={`w-full text-start px-2 py-1.5 text-sm rounded-sm hover:bg-accent hover:text-accent-foreground cursor-pointer ${
                    opt.value === currentValue ? 'bg-accent text-accent-foreground' : ''
                  }`}
                  onClick={() => {
                    onChange(fieldKey, opt.value);
                    setOpen(false);
                    setSearch('');
                  }}
                >
                  {opt.label}
                </button>
              ))
            )}
          </div>

          {options.length >= 100 && (
            <div className="border-t px-3 py-2">
              <p className="text-xs text-muted-foreground">
                {__('Showing first 100 results — type in the field to enter others', 'wp-sms')}
              </p>
            </div>
          )}
        </PopoverContent>
      </Popover>

      {selectedLabel && selectedLabel !== currentValue && (
        <p className="text-xs text-muted-foreground">{selectedLabel}</p>
      )}

      {error && !open && (
        <p className="text-xs text-destructive flex items-center gap-1">
          <AlertCircle className="h-3 w-3" />
          {__('Could not load options — you can type a value manually', 'wp-sms')}
        </p>
      )}

      {field.description && <FieldDescription>{field.description}</FieldDescription>}
    </Field>
  );
}
