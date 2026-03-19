import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { Dialog as DialogPrimitive } from 'radix-ui';
import { IntegrationIcon } from '@/components/integration-icon';
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
}

interface IntegrationGroup {
  name: string;
  icon: string;
  items: PickerItem[];
}

/** Combined TokenBadge + IntegrationPicker for selecting a trigger or action. */
export function IntegrationPickerToken({
  items,
  value,
  onSelect,
  placeholder,
  title,
  searchPlaceholder,
}: {
  items: PickerItem[];
  value: string;
  onSelect: (value: string) => void;
  placeholder: string;
  title: string;
  searchPlaceholder: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = useMemo(() => items.find((i) => i.value === value), [items, value]);

  return (
    <>
      <TokenBadge hasValue={!!value} onClick={() => setOpen(true)}>
        {selected ? (
          <>
            <IntegrationIcon icon={selected.icon} size="sm" className="mr-1.5" />
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
      />
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

  const navigableItems = isSearching ? searchResults : activeItems;

  const handleSelect = useCallback(
    (val: string) => {
      onSelect(val);
      onOpenChange(false);
    },
    [onSelect, onOpenChange],
  );

  const handleKeyDown = (e: React.KeyboardEvent) => {
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

  const renderItem = (item: PickerItem, i: number, showIcon: boolean) => (
    <button
      key={item.value}
      ref={(el) => { if (el) { itemRefs.current.set(i, el); } else { itemRefs.current.delete(i); } }}
      type="button"
      className={cn(
        'flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left transition-colors',
        focusIndex === i ? 'bg-accent' : 'hover:bg-accent',
      )}
      onClick={() => handleSelect(item.value)}
    >
      {showIcon && <IntegrationIcon icon={item.icon} size="sm" />}
      <div className="min-w-0 flex-1">
        <div className={cn('text-sm truncate', item.value === value && 'font-medium')}>
          {item.label}
        </div>
        {item.description && (
          <div className="text-xs text-muted-foreground truncate">{item.description}</div>
        )}
      </div>
      {item.value === value && <Check className="h-4 w-4 shrink-0 text-primary" />}
    </button>
  );

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-[9999] bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        <DialogPrimitive.Content
          className="fixed top-[50%] left-[50%] z-[9999] w-full max-w-lg translate-x-[-50%] translate-y-[-50%] rounded-lg border bg-background shadow-lg duration-200 overflow-hidden data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
          onKeyDown={handleKeyDown}
        >
          <DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>

          {/* Search bar with close button inline */}
          <div className="flex items-center gap-2 border-b px-3 py-2">
            <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
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

          {isSearching ? (
            <div className="max-h-72 overflow-y-auto p-1">
              {searchResults.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">No results found</p>
              ) : (
                searchResults.map((item, i) => renderItem(item, i, true))
              )}
            </div>
          ) : (
            <div className="flex" style={{ height: '320px' }}>
              {/* Left panel */}
              <div className="w-40 shrink-0 overflow-y-auto border-r p-1">
                {groups.map((group) => (
                  <button
                    key={group.name}
                    type="button"
                    className={cn(
                      'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors',
                      activeGroup === group.name
                        ? 'bg-accent font-medium'
                        : 'hover:bg-accent/50 text-muted-foreground',
                    )}
                    onClick={() => setActiveGroup(group.name)}
                  >
                    <IntegrationIcon icon={group.icon} size="sm" />
                    <span className="truncate">{group.name}</span>
                  </button>
                ))}
              </div>

              {/* Right panel */}
              <div className="flex-1 overflow-y-auto p-1">
                {activeItems.map((item, i) => renderItem(item, i, false))}
              </div>
            </div>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  );
}
