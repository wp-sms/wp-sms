import { __, sprintf } from '@wordpress/i18n';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import { IntegrationIcon } from '@/components/integration-icon';
import { Badge } from '@/components/ui/badge';
import { TokenBadge } from '@/components/flow-editor/sentence-builder/sentence-token';
import { cn, groupBy } from '@/lib/utils';
import { Check, Search, X } from 'lucide-react';

export interface PickerItem {
  value: string;
  label: string;
  description: string;
  group: string;
  icon: string;
}

interface IntegrationPickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  items: PickerItem[];
  value: string;
  onSelect: (value: string) => void;
  title: string;
  searchPlaceholder: string;
  storageKey?: string;
}


/** Combined TokenBadge + IntegrationPicker for selecting a trigger or action. */
export function IntegrationPickerToken({
  items,
  value,
  onSelect,
  placeholder,
  title,
  searchPlaceholder,
  storageKey,
}: {
  items: PickerItem[];
  value: string;
  onSelect: (value: string) => void;
  placeholder: string;
  title: string;
  searchPlaceholder: string;
  storageKey?: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = useMemo(() => items.find((i) => i.value === value), [items, value]);

  return (
    <>
      <TokenBadge hasValue={!!value} onClick={() => setOpen(true)}>
        {selected ? (
          <>
            <IntegrationIcon icon={selected.icon} size="sm" className="me-1.5" />
            {selected.label}
          </>
        ) : (
          placeholder
        )}
      </TokenBadge>
      <IntegrationPicker
        open={open}
        onOpenChange={setOpen}
        items={items}
        value={value}
        onSelect={onSelect}
        title={title}
        searchPlaceholder={searchPlaceholder}
        storageKey={storageKey}
      />
    </>
  );
}

// ── Recently-used helpers ──

const MAX_RECENT = 5;
const STORAGE_PREFIX = 'wpsms-picker-recent-';

function getRecentValues(storageKey: string | undefined): string[] {
  if (!storageKey) return [];
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + storageKey);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function saveRecentValue(storageKey: string | undefined, val: string) {
  if (!storageKey) return;
  const prev = getRecentValues(storageKey);
  const next = [val, ...prev.filter((v) => v !== val)].slice(0, MAX_RECENT);
  try {
    localStorage.setItem(STORAGE_PREFIX + storageKey, JSON.stringify(next));
  } catch { /* quota exceeded */ }
}

// ── Search highlighting ──

function highlightMatch(text: string, queryLower: string): React.ReactNode {
  if (!queryLower) return text;
  const idx = text.toLowerCase().indexOf(queryLower);
  if (idx === -1) return text;
  return (
    <>
      {text.slice(0, idx)}
      <mark className="bg-primary/15 text-foreground rounded-sm px-0.5">{text.slice(idx, idx + queryLower.length)}</mark>
      {text.slice(idx + queryLower.length)}
    </>
  );
}

export function IntegrationPicker({
  open,
  onOpenChange,
  items,
  value,
  onSelect,
  title,
  searchPlaceholder,
  storageKey,
}: IntegrationPickerProps) {
  const [search, setSearch] = useState('');
  const [activeGroup, setActiveGroup] = useState('');
  const [focusIndex, setFocusIndex] = useState(-1);
  const searchRef = useRef<HTMLInputElement>(null);
  const itemRefs = useRef<Map<number, HTMLButtonElement>>(new Map());

  const groups = useMemo(() => {
    const grouped = groupBy(items, (item) => item.group);
    return Object.entries(grouped).map(([name, groupItems]) => ({
      name,
      icon: groupItems[0].icon,
      items: groupItems,
    }));
  }, [items]);

  // Reset state when dialog opens
  useEffect(() => {
    if (open) {
      setSearch('');
      setFocusIndex(-1);
      if (value) {
        const currentItem = items.find((i) => i.value === value);
        if (currentItem) {
          setActiveGroup(currentItem.group);
          return;
        }
      }
      setActiveGroup(groups[0]?.name ?? '');
    }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (open) {
      requestAnimationFrame(() => searchRef.current?.focus());
    }
  }, [open]);

  const isSearching = search.trim().length > 0;
  const searchLower = search.toLowerCase();

  const searchResults = useMemo(() => {
    if (!isSearching) return [];
    return items.filter(
      (item) =>
        item.label.toLowerCase().includes(searchLower) ||
        item.group.toLowerCase().includes(searchLower) ||
        item.description.toLowerCase().includes(searchLower),
    );
  }, [items, searchLower, isSearching]);

  const activeItems = useMemo(() => {
    const group = groups.find((g) => g.name === activeGroup);
    return group?.items ?? [];
  }, [groups, activeGroup]);

  const recentItems = useMemo(() => {
    if (!storageKey) return [];
    const vals = getRecentValues(storageKey);
    return vals.map((v) => items.find((i) => i.value === v)).filter(Boolean) as PickerItem[];
  }, [storageKey, items]);

  const navigableItems = isSearching ? searchResults : activeItems;

  const handleSelect = useCallback(
    (val: string) => {
      saveRecentValue(storageKey, val);
      onSelect(val);
      onOpenChange(false);
    },
    [onSelect, onOpenChange, storageKey],
  );

  const handleKeyDown = (e: React.KeyboardEvent) => {
    // First Escape clears search, second closes dialog
    if (e.key === 'Escape') {
      if (isSearching) {
        e.preventDefault();
        e.stopPropagation();
        setSearch('');
        return;
      }
      // Let Radix handle the second Escape to close dialog
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setFocusIndex((prev) => {
        const next = Math.min(prev + 1, navigableItems.length - 1);
        itemRefs.current.get(next)?.scrollIntoView({ block: 'nearest' });
        return next;
      });
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setFocusIndex((prev) => {
        const next = Math.max(prev - 1, 0);
        itemRefs.current.get(next)?.scrollIntoView({ block: 'nearest' });
        return next;
      });
    } else if (e.key === 'Enter' && focusIndex >= 0 && focusIndex < navigableItems.length) {
      e.preventDefault();
      handleSelect(navigableItems[focusIndex].value);
    }
  };

  useEffect(() => {
    setFocusIndex(-1);
    itemRefs.current.clear();
  }, [isSearching, activeGroup]);

  // navIndex: keyboard nav index (-1 = skip), staggerIndex: animation delay order
  const renderItem = (item: PickerItem, navIndex: number, staggerIndex: number, opts?: { highlight?: boolean; showGroup?: boolean; stagger?: boolean; keyPrefix?: string }) => (
    <button
      key={opts?.keyPrefix ? `${opts.keyPrefix}-${item.value}` : item.value}
      ref={navIndex >= 0 ? (el) => { if (el) { itemRefs.current.set(navIndex, el); } else { itemRefs.current.delete(navIndex); } } : undefined}
      type="button"
      className={cn(
        'flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-start transition-colors',
        opts?.stagger && 'animate-fade-up',
        navIndex >= 0 && focusIndex === navIndex
          ? 'bg-accent ring-1 ring-ring/30'
          : item.value === value
            ? 'bg-primary/5'
            : 'hover:bg-accent',
      )}
      style={opts?.stagger ? { animationDelay: `${staggerIndex * 30}ms` } : undefined}
      onClick={() => handleSelect(item.value)}
    >
      <IntegrationIcon icon={item.icon} size="md" />
      <div className="min-w-0 flex-1">
        <div className={cn('text-sm truncate', item.value === value && 'font-medium')}>
          {opts?.highlight ? highlightMatch(item.label, searchLower) : item.label}
        </div>
        {item.description && (
          <div className="text-xs text-muted-foreground truncate mt-0.5">
            {opts?.highlight ? highlightMatch(item.description, searchLower) : item.description}
          </div>
        )}
      </div>
      {opts?.showGroup && (
        <Badge variant="neutral" className="text-[10px] shrink-0">{item.group}</Badge>
      )}
      {item.value === value && <Check className="h-4 w-4 shrink-0 text-primary" />}
    </button>
  );

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-[100100] bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        <DialogPrimitive.Content
          className="fixed top-[50%] start-1/2 z-[100100] w-[calc(100vw-2rem)] sm:w-full max-w-2xl -translate-x-1/2 rtl:translate-x-1/2 translate-y-[-50%] rounded-lg border bg-background shadow-lg duration-200 overflow-hidden data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
          onKeyDown={handleKeyDown}
        >
          <DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>

          {/* Search bar with title label */}
          <div className="border-b">
            <div className="px-4 pt-3 pb-1">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{title}</span>
            </div>
            <div className="flex items-center gap-2.5 px-4 py-3">
              <Search className="h-5 w-5 shrink-0 text-muted-foreground" />
              <input
                ref={searchRef}
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={searchPlaceholder}
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
              />
              <DialogPrimitive.Close className="rounded-sm opacity-50 ring-offset-background transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden">
                <X className="h-4 w-4" />
                <span className="sr-only">Close</span>
              </DialogPrimitive.Close>
            </div>
          </div>

          {isSearching ? (
            <div className="max-h-[380px] overflow-y-auto p-1.5 picker-scroll">
              {searchResults.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-center">
                  <Search className="h-10 w-10 text-muted-foreground/30 mb-3" />
                  <p className="text-sm font-medium text-foreground">{sprintf(__('No results for "%s"', 'wp-sms'), search)}</p>
                  <p className="text-xs text-muted-foreground mt-1">{__('Try a different search term', 'wp-sms')}</p>
                </div>
              ) : (
                searchResults.map((item, i) => renderItem(item, i, i, { highlight: true, showGroup: true, stagger: true }))
              )}
            </div>
          ) : (
            <div className="flex flex-col sm:flex-row" style={{ height: '380px' }}>
              {/* Mobile: horizontal pill row */}
              <div className="sm:hidden shrink-0 overflow-x-auto border-b">
                <div className="flex gap-1 p-1.5">
                  {groups.map((group) => (
                    <button
                      key={group.name}
                      type="button"
                      className={cn(
                        'flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs transition-colors',
                        activeGroup === group.name
                          ? 'bg-primary/10 font-medium text-primary'
                          : 'bg-accent/50 text-muted-foreground hover:bg-accent',
                      )}
                      onClick={() => setActiveGroup(group.name)}
                    >
                      <IntegrationIcon icon={group.icon} size="sm" />
                      <span>{group.name}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Desktop: vertical sidebar */}
              <div className="hidden sm:block w-48 shrink-0 overflow-y-auto border-e p-1.5 picker-scroll">
                {groups.map((group) => (
                  <button
                    key={group.name}
                    type="button"
                    className={cn(
                      'flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-start text-sm transition-colors',
                      activeGroup === group.name
                        ? 'bg-primary/5 font-medium border-s-2 border-primary'
                        : 'hover:bg-accent/50 text-muted-foreground',
                    )}
                    onClick={() => setActiveGroup(group.name)}
                  >
                    <IntegrationIcon icon={group.icon} size="md" />
                    <span className="truncate flex-1">{group.name}</span>
                    <span className="text-[10px] text-muted-foreground tabular-nums">{group.items.length}</span>
                  </button>
                ))}
              </div>

              {/* Right panel */}
              <div key={activeGroup} className="flex-1 min-h-0 overflow-y-auto p-1.5 picker-scroll">
                {/* Recently used section */}
                {recentItems.length > 0 && (
                  <>
                    <div className="px-2 pt-1 pb-1.5">
                      <span className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">{__('Recent', 'wp-sms')}</span>
                    </div>
                    {recentItems.map((item, i) => renderItem(item, -1, i, { stagger: true, keyPrefix: 'recent' }))}
                    <div className="mx-2 my-1.5 border-t" />
                  </>
                )}
                {activeItems.map((item, i) => renderItem(item, i, recentItems.length + i, { stagger: true }))}
              </div>
            </div>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  );
}
