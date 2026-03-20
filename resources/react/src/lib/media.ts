/**
 * Open the WordPress media library picker.
 *
 * @param title   Dialog title shown to the admin
 * @param onSelect  Called with the chosen image URL
 */
export function openMediaLibrary(title: string, onSelect: (url: string) => void) {
  const win = window as unknown as Record<string, unknown>;
  if (typeof window === 'undefined' || !win.wp) return;
  const wp = win.wp as Record<string, unknown>;
  const media = wp.media as ((opts: Record<string, unknown>) => Record<string, unknown>) | undefined;
  if (!media) return;

  const frame = media({
    title,
    button: { text: 'Use this image' },
    multiple: false,
    library: { type: 'image' },
  });

  (frame.on as (evt: string, cb: () => void) => void)('select', () => {
    const state = (frame.state as () => Record<string, unknown>)();
    const selection = (state.get as (key: string) => Record<string, unknown>)('selection');
    const attachment = (selection.first as () => Record<string, unknown>)();
    const json = (attachment.toJSON as () => Record<string, unknown>)();
    const sizes = json.sizes as Record<string, { url: string }> | undefined;
    const url = sizes?.medium?.url ?? sizes?.thumbnail?.url ?? (json.url as string);
    onSelect(url);
  });

  (frame.open as () => void)();
}
