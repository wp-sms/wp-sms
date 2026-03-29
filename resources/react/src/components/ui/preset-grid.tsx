import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';

interface PresetGridProps<T extends { id: string; name: string }> {
  presets: T[];
  activePresetId: string;
  onSelect: (preset: T) => void;
  renderPreview: (preset: T) => React.ReactNode;
  columns?: string;
}

export function PresetGrid<T extends { id: string; name: string }>({
  presets,
  activePresetId,
  onSelect,
  renderPreview,
  columns = 'grid-cols-3 sm:grid-cols-4',
}: PresetGridProps<T>) {
  return (
    <div className={cn('grid gap-2', columns)}>
      {presets.map((preset) => (
        <button
          key={preset.id}
          type="button"
          title={preset.name}
          onClick={() => onSelect(preset)}
          className={cn(
            'group relative flex flex-col overflow-hidden rounded-lg border p-2 text-start transition-all',
            activePresetId === preset.id
              ? 'border-primary bg-primary/10 ring-2 ring-primary/20'
              : 'border-input hover:border-foreground/20',
          )}
        >
          {renderPreview(preset)}
          <span className="mt-1.5 text-[11px] font-medium leading-none text-muted-foreground group-hover:text-foreground">
            {preset.name}
          </span>
        </button>
      ))}
      {activePresetId === 'custom' && (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-input p-2">
          <span className="text-[11px] font-medium text-muted-foreground">{__('Custom', 'wp-sms')}</span>
        </div>
      )}
    </div>
  );
}
