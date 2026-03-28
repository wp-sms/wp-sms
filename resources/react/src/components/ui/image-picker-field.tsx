import { X, Image } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';

interface ImagePickerFieldProps {
  value: string;
  title: string;
  onSelect: (url: string) => void;
  onClear: () => void;
  alt?: string;
}

export function ImagePickerField({ value, title, onSelect, onClear, alt = '' }: ImagePickerFieldProps) {
  const handleOpen = () => openMediaLibrary(title, onSelect);

  if (value) {
    return (
      <div
        className="group relative h-24 w-full max-w-xs cursor-pointer overflow-hidden rounded-lg border border-input"
        onClick={handleOpen}
      >
        <img src={value} alt={alt} className="h-full w-full object-cover" />
        <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
          <span className="text-xs font-medium text-white">Change</span>
        </div>
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); onClear(); }}
          className="absolute right-1.5 top-1.5 rounded-md bg-destructive p-0.5 text-destructive-foreground shadow-sm hover:bg-destructive/90"
        >
          <X className="h-3 w-3" />
        </button>
      </div>
    );
  }

  return (
    <div
      className="flex h-24 w-full max-w-xs cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-input transition-colors hover:border-primary/30 hover:bg-primary/5"
      onClick={handleOpen}
    >
      <Image className="h-5 w-5 text-muted-foreground/50" />
      <span className="text-xs text-muted-foreground">Click to upload</span>
    </div>
  );
}
