import { useState, useEffect, useMemo, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { Command } from 'cmdk';
import { CheckIcon, ChevronDownIcon, Loader2 } from 'lucide-react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { SYSTEM_FONTS, GOOGLE_FONTS, ALL_FONTS } from './font-list';

const CURATED_NAMES = new Set(ALL_FONTS.map((f) => f.value));
const MAX_CATALOG_RESULTS = 50;
const GROUP_CLASS = '[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:text-muted-foreground';
const ITEM_CLASS = 'relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pe-8 ps-2 text-sm outline-hidden select-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground';

interface FontPickerProps {
  value: string;
  onChange: (value: { font_family: string; google_font: boolean }) => void;
}

export function FontPicker({ value, onChange }: FontPickerProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [catalog, setCatalog] = useState<string[]>([]);
  const [catalogLoading, setCatalogLoading] = useState(false);
  const catalogLoaded = useRef(false);

  const displayLabel = useMemo(() => {
    const font = ALL_FONTS.find((f) => f.value === value);
    return font?.label ?? value ?? __('Select a font', 'wp-sms');
  }, [value]);

  // Lazy-load the full catalog on first open
  useEffect(() => {
    if (!open || catalogLoaded.current) return;
    catalogLoaded.current = true;
    setCatalogLoading(true);
    const catalogUrl = `${window.wpSmsSettings.pluginUrl}public/app/google-fonts-catalog.json`;
    fetch(catalogUrl)
      .then((r) => r.json())
      .then((data: string[]) => setCatalog(data))
      .catch(() => {}) // Silently fall back to curated-only
      .finally(() => setCatalogLoading(false));
  }, [open]);

  const query = search.trim().toLowerCase();

  const filteredSystem = useMemo(
    () => SYSTEM_FONTS.filter((f) => !query || f.label.toLowerCase().includes(query)),
    [query],
  );

  const filteredCurated = useMemo(
    () => GOOGLE_FONTS.filter((f) => !query || f.label.toLowerCase().includes(query)),
    [query],
  );

  const filteredCatalog = useMemo(() => {
    if (query.length < 2 || !catalog.length) return [];
    return catalog
      .filter((name) => !CURATED_NAMES.has(name) && name.toLowerCase().includes(query))
      .slice(0, MAX_CATALOG_RESULTS);
  }, [query, catalog]);

  const hasResults = filteredSystem.length > 0 || filteredCurated.length > 0 || filteredCatalog.length > 0;

  function select(fontValue: string, isGoogle: boolean) {
    onChange({ font_family: fontValue, google_font: isGoogle });
    setOpen(false);
    setSearch('');
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          role="combobox"
          aria-expanded={open}
          className="flex h-9 w-full items-center justify-between gap-2 rounded-md border-2 border-input bg-card px-3 py-2 text-sm whitespace-nowrap transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/12 [&_svg]:pointer-events-none [&_svg]:shrink-0"
        >
          <span className="truncate">{displayLabel}</span>
          <ChevronDownIcon className="size-4 opacity-50" />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
        <Command
          shouldFilter={false}
          className="flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground"
        >
          <div className="flex items-center border-b px-3">
            <Command.Input
              value={search}
              onValueChange={setSearch}
              placeholder={__('Search fonts...', 'wp-sms')}
              className="flex h-9 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>
          <Command.List className="max-h-60 overflow-y-auto overflow-x-hidden p-1">
            {!hasResults && !catalogLoading && (
              <Command.Empty className="py-4 text-center text-sm text-muted-foreground">
                {__('No fonts found.', 'wp-sms')}
              </Command.Empty>
            )}

            {filteredSystem.length > 0 && (
              <Command.Group heading={__('System Fonts', 'wp-sms')} className={GROUP_CLASS}>
                {filteredSystem.map((font) => (
                  <Command.Item
                    key={font.value}
                    value={font.value}
                    onSelect={() => select(font.value, false)}
                    className={ITEM_CLASS}
                  >
                    {font.label}
                    {value === font.value && <CheckIcon className="absolute end-2 size-4" />}
                  </Command.Item>
                ))}
              </Command.Group>
            )}

            {filteredCurated.length > 0 && (
              <Command.Group heading={__('Popular Google Fonts', 'wp-sms')} className={GROUP_CLASS}>
                {filteredCurated.map((font) => (
                  <Command.Item
                    key={font.value}
                    value={font.value}
                    onSelect={() => select(font.value, true)}
                    className={ITEM_CLASS}
                  >
                    {font.label}
                    {value === font.value && <CheckIcon className="absolute end-2 size-4" />}
                  </Command.Item>
                ))}
              </Command.Group>
            )}

            {filteredCatalog.length > 0 && (
              <Command.Group heading={__('More Google Fonts', 'wp-sms')} className={GROUP_CLASS}>
                {filteredCatalog.map((name) => (
                  <Command.Item
                    key={name}
                    value={name}
                    onSelect={() => select(name, true)}
                    className={ITEM_CLASS}
                  >
                    {name}
                    {value === name && <CheckIcon className="absolute end-2 size-4" />}
                  </Command.Item>
                ))}
              </Command.Group>
            )}

            {catalogLoading && query.length >= 2 && (
              <div className="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                {__('Loading fonts...', 'wp-sms')}
              </div>
            )}
          </Command.List>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
