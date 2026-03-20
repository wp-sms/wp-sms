import { useEffect, useRef, useState, useCallback } from 'react';
import { Smartphone, Tablet, Monitor, ExternalLink } from 'lucide-react';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import type { BrandingSettings } from '@/lib/api';

const PAGE_OPTIONS = [
  { value: '/login', label: 'Login' },
  { value: '/register', label: 'Register' },
  { value: '/forgot-password', label: 'Forgot Password' },
  { value: '/', label: 'Account' },
] as const;

type PageRoute = (typeof PAGE_OPTIONS)[number]['value'];

const DEVICE_PRESETS = [
  { value: 'mobile' as const, label: 'Mobile', width: 375, icon: <Smartphone className="h-4 w-4" /> },
  { value: 'tablet' as const, label: 'Tablet', width: 768, icon: <Tablet className="h-4 w-4" /> },
  { value: 'desktop' as const, label: 'Desktop', width: 1024, icon: <Monitor className="h-4 w-4" /> },
];

type DevicePreset = (typeof DEVICE_PRESETS)[number]['value'];

const CONTAINER_HEIGHT = 700;

interface BrandingPreviewProps {
  branding: BrandingSettings;
  baseUrl: string;
}

export function BrandingPreview({ branding, baseUrl }: BrandingPreviewProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [ready, setReady] = useState(false);
  const [error, setError] = useState(false);
  const [page, setPage] = useState<PageRoute>('/login');
  const [device, setDevice] = useState<DevicePreset>('mobile');
  const [containerWidth, setContainerWidth] = useState(540);

  const selectedPreset = DEVICE_PRESETS.find((d) => d.value === device)!;
  const iframeWidth = selectedPreset.width;
  const scale = Math.min(containerWidth / iframeWidth, 1);
  const iframeHeight = Math.round(CONTAINER_HEIGHT / scale);

  // Measure container width
  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    const observer = new ResizeObserver(([entry]) => {
      const w = Math.round(entry.contentRect.width);
      setContainerWidth((prev) => (prev === w ? prev : w));
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  // Listen for iframe ready signal
  useEffect(() => {
    function handleMessage(e: MessageEvent) {
      if (e.data?.type === 'wsms-branding-preview-ready') {
        setReady(true);
      }
    }
    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, []);

  // Detect iframe load errors
  useEffect(() => {
    const timer = setTimeout(() => {
      if (!ready) setError(true);
    }, 8000);
    return () => clearTimeout(timer);
  }, [ready]);

  // Send branding updates to iframe via postMessage
  useEffect(() => {
    if (!ready || !iframeRef.current?.contentWindow) return;
    iframeRef.current.contentWindow.postMessage(
      { type: 'wsms-branding-preview', branding },
      '*'
    );
  }, [branding, ready]);

  // Navigate iframe when page changes
  const handlePageChange = useCallback(
    (route: PageRoute) => {
      setPage(route);
      if (!ready || !iframeRef.current?.contentWindow) return;
      iframeRef.current.contentWindow.postMessage(
        { type: 'wsms-branding-preview-navigate', route },
        '*'
      );
    },
    [ready]
  );

  const previewUrl = `${baseUrl}/login?preview=1`;
  const fullscreenUrl = `${baseUrl}${page}?preview=1`;

  if (error && !ready) {
    return (
      <div className="flex h-full items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/20 p-8">
        <div className="text-center">
          <p className="text-sm font-medium text-muted-foreground">Preview unavailable</p>
          <p className="mt-1 text-xs text-muted-foreground/70">
            Your security settings may block iframe loading.
          </p>
          <a
            href={previewUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-2 inline-block text-xs text-primary hover:underline"
          >
            Open auth page in new tab
          </a>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {/* Toolbar */}
      <div className="flex items-center gap-3">
        {/* Page switcher */}
        <select
          value={page}
          onChange={(e) => handlePageChange(e.target.value as PageRoute)}
          className="h-9 appearance-none rounded-md border border-input bg-background bg-[length:16px_16px] bg-[right_8px_center] bg-no-repeat pl-3 pr-8 text-sm"
          style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")` }}
        >
          {PAGE_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        {/* Device presets */}
        <SegmentedGroup
          value={device}
          onChange={setDevice}
          options={DEVICE_PRESETS}
        />

        <div className="flex-1" />

        {/* Fullscreen button */}
        <button
          type="button"
          title="Open in new tab"
          onClick={() => window.open(fullscreenUrl, '_blank')}
          className="flex h-9 w-9 items-center justify-center rounded-md border border-input text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <ExternalLink className="h-4 w-4" />
        </button>
      </div>

      {/* Preview */}
      <div className="relative overflow-hidden rounded-lg border bg-muted/30" ref={containerRef}>
        {!ready && (
          <div className="absolute inset-0 z-10 flex items-center justify-center bg-muted/80">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        )}
        <div
          className="relative overflow-hidden"
          style={{ height: `${CONTAINER_HEIGHT}px` }}
        >
          <iframe
            ref={iframeRef}
            src={previewUrl}
            title="Auth page preview"
            className="origin-top-left border-0"
            style={{
              width: `${iframeWidth}px`,
              height: `${iframeHeight}px`,
              transform: `scale(${scale})`,
              transformOrigin: 'top left',
              pointerEvents: ready ? 'auto' : 'none',
            }}
          />
        </div>
      </div>
    </div>
  );
}
