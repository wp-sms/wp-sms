import { useCallback, useEffect, useRef, useState, useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { Search, CheckCircle2, XCircle, Loader2, ChevronDown, ExternalLink, Info, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { GatewayConfigForm } from '@/components/gateway-config-form';
import type { UseGatewaysReturn } from '@/hooks/use-gateways';
import { cn } from '@/lib/utils';
import { getConfig, type Gateway, type GatewayConfig, type OnboardingGoal } from '@/lib/api';
import { channelLabel } from '@/lib/channel';

const PRIMARY_CHANNELS = ['sms', 'email'];
const SAVE_DEBOUNCE_MS = 600;

function getLocaleRegions(): string[] {
  const locale = getConfig().locale ?? 'en_US';
  const country = locale.split('_')[1]?.toUpperCase();
  const regionMap: Record<string, string[]> = {
    TR: ['TR'], DE: ['EU', 'DE'], FR: ['EU', 'FR'], JP: ['JP'],
    TH: ['TH'], TW: ['TW'], ID: ['ID'], NL: ['EU', 'NL'],
    ES: ['EU', 'ES'], IT: ['EU', 'IT'], PT: ['EU', 'PT'],
  };
  return regionMap[country ?? ''] ?? [];
}

function getRecommended(gateways: Gateway[], goals: OnboardingGoal[]): Gateway[] {
  const localeRegions = getLocaleRegions();
  const wantsAuth = goals.includes('auth');
  const wantsSms = goals.includes('notifications') || goals.includes('campaigns');

  const scored = gateways.map((gw) => {
    let score = 0;
    if (wantsSms && gw.supported_channels.includes('sms')) score += 3;
    if (wantsAuth && gw.supported_channels.includes('email')) score += 3;
    if (gw.supported_channels.length > 1) score += 1;
    const regions = gw.metadata?.regions ?? [];
    if (localeRegions.length > 0 && regions.some((r: string) => localeRegions.includes(r))) score += 2;
    if (regions.length === 0 || regions.includes('global')) score += 1;
    return { gw, score };
  });

  scored.sort((a, b) => b.score - a.score);
  return scored.slice(0, 3).map((s) => s.gw);
}

interface GatewayStepProps {
  goals: OnboardingGoal[];
  gatewaysHook: UseGatewaysReturn;
}

export function GatewayStep({ goals, gatewaysHook }: GatewayStepProps) {
  const { gateways, loading: gatewaysLoading, updateConfig, testConnection } = gatewaysHook;
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [showAll, setShowAll] = useState(false);
  const [search, setSearch] = useState('');
  const [channelFilter, setChannelFilter] = useState<string>('all');
  const [testStatus, setTestStatus] = useState<'idle' | 'testing' | 'success' | 'error'>('idle');
  const [testError, setTestError] = useState<string | null>(null);

  // Local draft of the selected gateway's config. The form reads from this
  // so typing is instant; saves are debounced (SAVE_DEBOUNCE_MS) to avoid
  // one API PUT per keystroke. draftRef mirrors draftConfig so the unmount
  // cleanup can read the latest value without stale-closure issues.
  const [draftConfig, setDraftConfig] = useState<GatewayConfig | null>(null);
  const draftRef = useRef<GatewayConfig | null>(null);
  const savingIdRef = useRef<string | null>(null);
  const saveTimerRef = useRef<ReturnType<typeof setTimeout>>();

  const recommended = useMemo(() => getRecommended(gateways, goals), [gateways, goals]);
  const selectedGateway = gateways.find((g) => g.id === selectedId);

  const filteredGateways = useMemo(() => {
    let list = gateways;
    if (channelFilter !== 'all') list = list.filter((g) => g.supported_channels.includes(channelFilter));
    if (search) { const q = search.toLowerCase(); list = list.filter((g) => g.name.toLowerCase().includes(q)); }
    return list;
  }, [gateways, channelFilter, search]);

  const availableChannels = useMemo(() => {
    const all = new Set<string>();
    gateways.forEach((g) => g.supported_channels.forEach((c) => all.add(c)));
    return PRIMARY_CHANNELS.filter((ch) => all.has(ch));
  }, [gateways]);

  // Flush any pending debounced save to the server. Returns a promise that
  // resolves once the PUT completes so callers like Test Connection can
  // await it and guarantee the server has the latest typed values before
  // running their own request.
  //
  // Note: unlike the inline debounced path in handleFormChange, this flush
  // intentionally does not reset test status — callers handle that (e.g.
  // selectGateway sets idle itself, handleTestConnection keeps 'testing').
  const flushPendingSave = useCallback(async (): Promise<void> => {
    if (!saveTimerRef.current) return;
    clearTimeout(saveTimerRef.current);
    saveTimerRef.current = undefined;
    const pending = draftRef.current;
    const id = savingIdRef.current;
    savingIdRef.current = null;
    if (pending && id) {
      await updateConfig({ [id]: pending as unknown as Record<string, unknown> });
    }
  }, [updateConfig]);

  const selectGateway = (id: string | null) => {
    // Flush any pending edit on the previous gateway before switching so we
    // don't lose the user's in-flight typing. Fire-and-forget: switching
    // UI state should not block on the network.
    void flushPendingSave();
    setSelectedId(id);
    setTestStatus('idle');
    setTestError(null);
  };

  // Initialize the draft when the selected gateway changes (including the
  // first time one is selected). Intentionally keyed on selectedId, not on
  // selectedGateway.config, so a background refetch that returns the same
  // gateway does not overwrite in-progress edits.
  useEffect(() => {
    if (selectedGateway) {
      setDraftConfig(selectedGateway.config);
      draftRef.current = selectedGateway.config;
    } else {
      setDraftConfig(null);
      draftRef.current = null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId]);

  // Flush any pending save on wizard navigation / unmount so edits are not
  // lost when the user clicks Continue. Fire-and-forget: the promise chain
  // in updateConfig runs to completion regardless of mount state.
  useEffect(() => {
    return () => { void flushPendingSave(); };
  }, [flushPendingSave]);

  const handleFormChange = (config: GatewayConfig) => {
    if (!selectedId) return;
    setDraftConfig(config);
    draftRef.current = config;
    savingIdRef.current = selectedId;
    if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
    saveTimerRef.current = setTimeout(() => {
      saveTimerRef.current = undefined;
      const pending = draftRef.current;
      const id = savingIdRef.current;
      savingIdRef.current = null;
      if (pending && id) {
        void updateConfig({ [id]: pending as unknown as Record<string, unknown> });
        setTestStatus('idle');
        setTestError(null);
      }
    }, SAVE_DEBOUNCE_MS);
  };

  const handleTestConnection = async () => {
    if (!selectedId) return;
    // Mark the button disabled up-front so it stays disabled during the
    // flush window too — otherwise a second click could fire a duplicate
    // test against the half-saved credentials.
    setTestStatus('testing');
    setTestError(null);
    try {
      // Await the flush so the test runs against the values the user just
      // typed, not the previously-saved server state. Without the await the
      // POST races the PUT and can hit stale credentials.
      await flushPendingSave();
      const result = await testConnection(selectedId);
      setTestStatus(result.success ? 'success' : 'error');
      if (!result.success) setTestError(result.message);
    } catch {
      setTestStatus('error');
      setTestError(__('Connection test failed.', 'wp-sms'));
    }
  };

  if (gatewaysLoading) {
    return (
      <div className="space-y-4">
        <div className="h-5 w-48 bg-muted animate-pulse rounded" />
        <div className="grid grid-cols-3 gap-2.5">
          {[1, 2, 3].map((i) => <div key={i} className="h-[100px] bg-muted animate-pulse rounded-[var(--radius-lg)]" />)}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      {/* Title */}
      <div>
        <h1 className="text-xl font-extrabold tracking-[-0.02em]">
          {__('Connect a messaging gateway', 'wp-sms')}
        </h1>
        <p className="text-[14px] text-muted-foreground mt-1.5 leading-relaxed">
          {__('Pick a provider to get started. You can add more later.', 'wp-sms')}
        </p>
      </div>

      {/* Recommended cards — only when no gateway is selected for config */}
      {!selectedGateway && (
        <>
          <div>
            <div className="text-xs font-bold uppercase tracking-[.06em] text-muted-foreground mb-2.5">
              {__('Recommended for you', 'wp-sms')}
            </div>
            <div className="grid grid-cols-3 gap-2.5">
              {recommended.map((gw) => (
                <RecommendedCard key={gw.id} gateway={gw} selected={selectedId === gw.id} onClick={() => selectGateway(gw.id)} />
              ))}
            </div>
          </div>

          {/* Browse all — collapsible picker */}
          <div>
            <button
              type="button"
              onClick={() => setShowAll(!showAll)}
              className="flex w-full items-center justify-between py-2.5 px-4 text-[14px] font-medium text-muted-foreground hover:text-foreground transition-colors border-2 rounded-[var(--radius-lg)] bg-card"
            >
              <span>{__('Browse all gateways', 'wp-sms')}</span>
              <span className="flex items-center gap-1.5">
                <Badge variant="secondary">{gateways.length}</Badge>
                <ChevronDown className={cn('size-4 transition-transform duration-[120ms]', showAll && 'rotate-180')} />
              </span>
            </button>

            {showAll && (
              <div className="mt-4 border-2 rounded-[var(--radius-lg)] bg-card overflow-hidden animate-fade-up">
                {/* Toolbar: search + channel tabs */}
                <div className="flex items-center border-b">
                  <div className="relative flex-1 min-w-[180px] border-e">
                    <Search className="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    <input
                      type="text"
                      className="w-full h-11 ps-9 pe-3 text-[14px] bg-transparent text-foreground placeholder:text-muted-foreground focus:outline-none focus:bg-primary/[0.02]"
                      placeholder={__('Search gateways...', 'wp-sms')}
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                  <div className="flex px-1">
                    {['all', ...availableChannels].map((ch) => (
                      <button
                        key={ch}
                        type="button"
                        onClick={() => setChannelFilter(ch)}
                        className={cn(
                          'px-3.5 py-3 text-xs font-bold border-b-2 transition-colors',
                          channelFilter === ch
                            ? 'text-primary border-primary'
                            : 'text-muted-foreground border-transparent hover:text-foreground',
                        )}
                      >
                        {ch === 'all' ? __('All', 'wp-sms') : channelLabel(ch)}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Gateway rows */}
                <div className="max-h-60 overflow-y-auto" style={{ scrollbarWidth: 'thin', scrollbarColor: 'var(--border) transparent' }}>
                  {filteredGateways.map((gw, i) => (
                    <button
                      key={gw.id}
                      type="button"
                      onClick={() => { selectGateway(gw.id); setShowAll(false); }}
                      className={cn(
                        'w-full flex items-center gap-3 px-4 py-2.5 text-start border-s-2 border-s-transparent transition-colors hover:bg-accent/60',
                        i > 0 && 'border-t border-border/30',
                        selectedId === gw.id && 'bg-primary/[0.02] border-s-primary',
                      )}
                    >
                      {gw.metadata?.icon ? (
                        <img src={gw.metadata.icon} alt="" className="size-6 rounded-[var(--radius)]" />
                      ) : (
                        <div className="size-6 rounded-[var(--radius)] bg-muted" />
                      )}
                      <span className="text-[14px] font-medium flex-1 min-w-0 truncate">{gw.name}</span>
                      <div className="flex gap-1 shrink-0">
                        {gw.supported_channels.map((ch) => (
                          <Badge key={ch} variant="neutral">{channelLabel(ch)}</Badge>
                        ))}
                        {(gw.metadata?.regions ?? []).map((r: string) => (
                          <Badge key={r} variant="secondary">{r}</Badge>
                        ))}
                      </div>
                    </button>
                  ))}
                  {filteredGateways.length === 0 && (
                    <p className="py-6 text-center text-[14px] text-muted-foreground">{__('No gateways found.', 'wp-sms')}</p>
                  )}
                </div>

                {/* Count footer */}
                <div className="px-4 py-2 border-t border-border/30 text-xs text-muted-foreground">
                  {filteredGateways.length} {__('of', 'wp-sms')} {gateways.length} {__('gateways', 'wp-sms')}
                </div>
              </div>
            )}
          </div>
        </>
      )}

      {/* Configuration panel — shown when a gateway is selected */}
      {selectedGateway && (
        <>
          {/* Back to list */}
          <button
            type="button"
            onClick={() => selectGateway(null)}
            className="text-[14px] font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="me-1 size-3.5 rtl:scale-x-[-1]" /> {__('Choose a different gateway', 'wp-sms')}
          </button>

          <div className="border-2 rounded-[var(--radius-lg)] bg-card overflow-hidden animate-fade-up">
            <div className="flex items-start justify-between px-5 py-4 border-b">
              <div>
                <div className="text-[14px] font-semibold mb-0.5">
                  {/* eslint-disable-next-line react/jsx-no-literals */}
                  {__('Configure', 'wp-sms')} {selectedGateway.name}
                </div>
                <div className="text-[14px] text-muted-foreground">{__('Enter your credentials to connect.', 'wp-sms')}</div>
              </div>
              {selectedGateway.metadata?.setup_url && (
                <a
                  href={selectedGateway.metadata.setup_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 text-[14px] font-medium text-primary shrink-0 hover:underline"
                >
                  <ExternalLink className="size-3.5" />
                  {__('Open Dashboard', 'wp-sms')}
                </a>
              )}
            </div>
            <div className="p-5 space-y-4">
              <GatewayConfigForm
                gatewayId={selectedGateway.id}
                schema={selectedGateway.config_schema}
                values={draftConfig ?? selectedGateway.config}
                supportedChannels={selectedGateway.supported_channels}
                onChange={handleFormChange}
              />

              <div className="flex items-center gap-3">
                <Button variant="outline" size="sm" onClick={handleTestConnection} disabled={testStatus === 'testing'}>
                  {testStatus === 'testing' && <Loader2 className="size-3.5 animate-spin" />}
                  {__('Test Connection', 'wp-sms')}
                </Button>
              </div>

              {testStatus === 'success' && (
                <div className="flex items-center gap-2 px-3.5 py-2.5 rounded-[var(--radius-lg)] border border-green-600/20 bg-green-600/[0.05] text-green-700 text-[14px]">
                  <CheckCircle2 className="size-4 shrink-0" />
                  <span className="font-semibold">{__('Connected', 'wp-sms')}</span>
                </div>
              )}
              {testStatus === 'error' && (
                <div className="flex items-center gap-2 px-3.5 py-2.5 rounded-[var(--radius-lg)] border border-destructive/20 bg-destructive/[0.05] text-destructive text-[14px]">
                  <XCircle className="size-4 shrink-0" />
                  <span>{testError || __('Connection failed', 'wp-sms')}</span>
                </div>
              )}

              {selectedGateway.metadata?.setup_url && (
                <a
                  href={selectedGateway.metadata.setup_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1.5 text-[14px] text-muted-foreground hover:text-primary transition-colors"
                >
                  <Info className="size-3.5" />
                  {__('How to get your credentials', 'wp-sms')}
                </a>
              )}
            </div>
          </div>
        </>
      )}

    </div>
  );
}

/** Compact recommended gateway card — matches mockup's .gw-rec (padding:14px) */
function RecommendedCard({ gateway, selected, onClick }: { gateway: Gateway; selected: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'p-3.5 bg-card border-2 rounded-[var(--radius-lg)] text-start transition-[border-color] duration-[120ms] cursor-pointer',
        selected
          ? 'border-primary border-s-[3px] bg-[#fef8f3]'
          : 'border-border hover:border-[color-mix(in_srgb,var(--border)_60%,#000)]',
      )}
    >
      {/* Name + avatar */}
      <div className="flex items-center gap-2.5 mb-2">
        {gateway.metadata?.icon ? (
          <img src={gateway.metadata.icon} alt="" className="size-7 rounded-[var(--radius)]" />
        ) : (
          <div className="size-7 rounded-[var(--radius)] bg-muted" />
        )}
        <span className="text-[14px] font-semibold leading-tight">{gateway.name}</span>
      </div>

      {/* Channel badges */}
      <div className="flex gap-1 flex-wrap">
        {gateway.supported_channels.map((ch) => (
          <Badge key={ch} variant="neutral">{channelLabel(ch)}</Badge>
        ))}
      </div>

      {/* Description — truncated to 2 lines */}
      {gateway.metadata?.description && (
        <p className="text-[14px] text-muted-foreground leading-snug mt-2 line-clamp-2">{gateway.metadata.description}</p>
      )}
    </button>
  );
}
