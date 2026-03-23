import { useState, useEffect, useCallback, useMemo, useRef, Fragment } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import {
  Globe, ShieldBan, Database, Search as SearchIcon,
  Phone, X, Check, ChevronDown, AlertTriangle, Loader2,
  Download, RefreshCw, Info, Smartphone,
} from 'lucide-react';
import { toggleArrayItem, PHONE_DISPLAY_MODES, type PhoneDisplayMode } from '@/lib/constants';

function getErrorMessage(err: unknown, fallback: string): string {
  return (err && typeof err === 'object' && 'message' in err)
    ? (err as { message: string }).message
    : fallback;
}

interface SectionSettings {
  enabled: boolean;
  mode: 'allow' | 'block';
  allowed_countries: string[];
}

interface NumberTypeSettings {
  enabled: boolean;
  blocked_types: string[];
}

interface EnhancedDbSettings {
  auto_update: boolean;
}

interface PhoneInputSettings {
  default_country: string;
  preferred_countries: string[];
  display_mode: PhoneDisplayMode;
}

interface PhoneRestrictionSettings {
  auth: SectionSettings;
  messaging: SectionSettings;
  number_type_blocking: NumberTypeSettings;
  enhanced_db: EnhancedDbSettings;
  phone_input: PhoneInputSettings;
}

interface DbStatus {
  installed: boolean;
  version: string | null;
  updated_at: string | null;
}

interface SettingsResponse {
  success: boolean;
  settings: PhoneRestrictionSettings;
  db_status: DbStatus;
}

interface CheckResult {
  success: boolean;
  country: string | null;
  country_name: string | null;
  number_type: string | null;
  auth: { allowed: boolean; reason: string | null };
  messaging: { allowed: boolean; reason: string | null };
}

const BLOCKABLE_TYPES = [
  { id: 'premium_rate', label: 'Premium Rate', description: 'High-cost numbers used for paid services' },
  { id: 'toll_free', label: 'Toll-Free', description: 'Free-to-call numbers, often used for businesses' },
  { id: 'shared_cost', label: 'Shared Cost', description: 'Cost shared between caller and receiver' },
] as const;

export function PhoneRestriction() {
  const [saved, setSaved] = useState<PhoneRestrictionSettings | null>(null);
  const [draft, setDraft] = useState<PhoneRestrictionSettings | null>(null);
  const [dbStatus, setDbStatus] = useState<DbStatus | null>(null);
  const [countries, setCountries] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [downloading, setDownloading] = useState(false);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      api.get<SettingsResponse>('phone-restriction/settings'),
      api.get<{ success: boolean; countries: Record<string, string> }>('phone-restriction/countries'),
    ])
      .then(([settingsRes, countriesRes]) => {
        if (cancelled) return;
        setSaved(settingsRes.settings);
        setDraft(settingsRes.settings);
        setDbStatus(settingsRes.db_status);
        setCountries(countriesRes.countries);
      })
      .catch(() => {
        if (!cancelled) toast.error('Failed to load phone restriction settings');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  const isDirty = useMemo(() => {
    return JSON.stringify(saved) !== JSON.stringify(draft);
  }, [saved, draft]);

  const updateSection = useCallback(<S extends keyof PhoneRestrictionSettings>(
    section: S,
    patch: Partial<PhoneRestrictionSettings[S]>,
  ) => {
    setDraft((prev) => prev ? { ...prev, [section]: { ...prev[section], ...patch } } : prev);
  }, []);

  const save = useCallback(async () => {
    if (!draft) return;
    setSaving(true);
    try {
      const res = await api.put<SettingsResponse>('phone-restriction/settings', draft);
      setSaved(res.settings);
      setDraft(res.settings);
      toast.success('Settings saved');
    } catch {
      toast.error('Failed to save settings');
    } finally {
      setSaving(false);
    }
  }, [draft]);

  const downloadDb = useCallback(async () => {
    setDownloading(true);
    try {
      const res = await api.post<{ success: boolean; message: string; db_status: DbStatus }>(
        'phone-restriction/db/download',
        {},
      );
      setDbStatus(res.db_status);
      toast.success(res.message || 'Database downloaded successfully');
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Failed to download database'));
    } finally {
      setDownloading(false);
    }
  }, []);

  if (loading || !draft) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PhoneInputConfigCard
        draft={draft}
        countries={countries}
        updateSection={updateSection}
      />

      <CountryRestrictionsCard
        draft={draft}
        countries={countries}
        updateSection={updateSection}
      />

      <NumberTypeBlockingCard
        draft={draft}
        dbStatus={dbStatus}
        updateSection={updateSection}
        onDownload={downloadDb}
        downloading={downloading}
      />

      <EnhancedDatabaseCard
        dbStatus={dbStatus}
        autoUpdate={draft.enhanced_db.auto_update}
        onToggleAutoUpdate={(v) => updateSection('enhanced_db', { auto_update: v })}
        onDownload={downloadDb}
        downloading={downloading}
      />

      <PhoneCheckerCard />

      <div className="flex justify-end">
        <Button onClick={save} disabled={!isDirty || saving}>
          {saving ? 'Saving...' : 'Save Settings'}
        </Button>
      </div>
    </div>
  );
}

function PhoneInputConfigCard({ draft, countries, updateSection }: {
  draft: PhoneRestrictionSettings;
  countries: Record<string, string>;
  updateSection: <S extends keyof PhoneRestrictionSettings>(s: S, p: Partial<PhoneRestrictionSettings[S]>) => void;
}) {
  const hasRestrictions = draft.auth.enabled || draft.messaging.enabled;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Smartphone className="h-4 w-4 text-muted-foreground" />
          Phone Input Defaults
        </CardTitle>
        <CardDescription>
          Configure the default country and preferred countries for phone number inputs across your site
        </CardDescription>
      </CardHeader>
      <CardContent className="border-t pt-4 space-y-4">
        <Field>
          <FieldLabel>Display Mode</FieldLabel>
          <FieldDescription>How the phone number and dial code are displayed in the input</FieldDescription>
          <RadioGroup
            value={draft.phone_input.display_mode}
            onValueChange={(v) => updateSection('phone_input', { display_mode: v as PhoneDisplayMode })}
            className="space-y-2 mt-1"
          >
            {PHONE_DISPLAY_MODES.map((mode) => (
              <div key={mode.value} className="flex items-center gap-2">
                <RadioGroupItem value={mode.value} id={`dm-${mode.value}`} />
                <Label htmlFor={`dm-${mode.value}`} className="text-sm font-normal">
                  {mode.label} — {mode.description}
                </Label>
              </div>
            ))}
          </RadioGroup>
          {draft.phone_input.display_mode === 'national' && !draft.phone_input.default_country && (
            <div className="flex items-start gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3 mt-2">
              <AlertTriangle className="h-4 w-4 mt-0.5 text-destructive shrink-0" />
              <p className="text-sm text-destructive">
                National mode works best with a default country set. Without one, the input will use the first available country which may confuse users.
              </p>
            </div>
          )}
        </Field>

        <Field>
          <FieldLabel>Default Country</FieldLabel>
          <FieldDescription>Pre-selected country when the phone input loads</FieldDescription>
          <SingleCountryPicker
            countries={countries}
            value={draft.phone_input.default_country}
            onChange={(v) => updateSection('phone_input', { default_country: v })}
          />
        </Field>

        <Field>
          <FieldLabel>Preferred Countries</FieldLabel>
          <FieldDescription>These countries appear at the top of the dropdown for quick access</FieldDescription>
          <CountryPicker
            countries={countries}
            selected={draft.phone_input.preferred_countries}
            onChange={(v) => updateSection('phone_input', { preferred_countries: v })}
            buttonLabel="Add preferred countries"
          />
        </Field>

        {hasRestrictions && (
          <div className="flex items-start gap-2 rounded-md border bg-muted/50 p-3">
            <Info className="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
            <p className="text-sm text-muted-foreground">
              The phone input dropdown is automatically filtered based on your Country Restriction settings below.
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function SingleCountryPicker({ countries, value, onChange }: {
  countries: Record<string, string>;
  value: string;
  onChange: (code: string) => void;
}) {
  const selectedName = value ? (countries[value] ?? value) : null;

  return (
    <div className="flex items-center gap-2">
      <CountryDropdown
        countries={countries}
        buttonLabel={selectedName ?? 'Select country'}
        isSelected={(code) => code === value}
        onSelect={(code) => onChange(code)}
        indicatorShape="radio"
        closeOnSelect
      />
      {value && (
        <button
          type="button"
          onClick={() => onChange('')}
          className="rounded-full p-1 hover:bg-muted text-muted-foreground"
          title="Clear selection"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      )}
    </div>
  );
}

function CountryRestrictionsCard({ draft, countries, updateSection }: {
  draft: PhoneRestrictionSettings;
  countries: Record<string, string>;
  updateSection: <S extends keyof PhoneRestrictionSettings>(s: S, p: Partial<PhoneRestrictionSettings[S]>) => void;
}) {
  return (
    <Card active={draft.auth.enabled || draft.messaging.enabled}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Globe className="h-4 w-4 text-muted-foreground" />
          Country Restrictions
        </CardTitle>
        <CardDescription>
          Restrict phone numbers to specific countries for authentication and messaging
        </CardDescription>
      </CardHeader>
      <CardContent className="border-t pt-4 space-y-6">
        <CountrySection
          label="Authentication"
          description="Restrict which countries can register or verify via phone"
          enabled={draft.auth.enabled}
          mode={draft.auth.mode}
          selectedCountries={draft.auth.allowed_countries}
          countries={countries}
          onToggle={(v) => updateSection('auth', { enabled: v })}
          onModeChange={(v) => updateSection('auth', { mode: v })}
          onCountriesChange={(v) => updateSection('auth', { allowed_countries: v })}
        />

        <div className="border-t" />

        <CountrySection
          label="Messaging"
          description="Restrict which countries can receive messages"
          enabled={draft.messaging.enabled}
          mode={draft.messaging.mode}
          selectedCountries={draft.messaging.allowed_countries}
          countries={countries}
          onToggle={(v) => updateSection('messaging', { enabled: v })}
          onModeChange={(v) => updateSection('messaging', { mode: v })}
          onCountriesChange={(v) => updateSection('messaging', { allowed_countries: v })}
        />
      </CardContent>
    </Card>
  );
}

function CountrySection({ label, description, enabled, mode, selectedCountries, countries, onToggle, onModeChange, onCountriesChange }: {
  label: string;
  description: string;
  enabled: boolean;
  mode: 'allow' | 'block';
  selectedCountries: string[];
  countries: Record<string, string>;
  onToggle: (v: boolean) => void;
  onModeChange: (v: 'allow' | 'block') => void;
  onCountriesChange: (v: string[]) => void;
}) {
  const emptyHint = mode === 'allow'
    ? 'No countries selected — all phone numbers will be blocked when this is enabled'
    : 'No countries blocked — all countries are allowed';

  return (
    <div className="space-y-3">
      <Field orientation="horizontal">
        <div className="flex-1">
          <FieldLabel>{label}</FieldLabel>
          <FieldDescription>{description}</FieldDescription>
        </div>
        <Switch checked={enabled} onCheckedChange={onToggle} />
      </Field>
      {enabled && (
        <div className="space-y-3">
          <RadioGroup
            value={mode}
            onValueChange={(v) => onModeChange(v as 'allow' | 'block')}
            className="flex gap-4"
          >
            <div className="flex items-center gap-1.5">
              <RadioGroupItem value="allow" id={`${label}-mode-allow`} />
              <Label htmlFor={`${label}-mode-allow`} className="text-sm font-normal">Allow only selected countries</Label>
            </div>
            <div className="flex items-center gap-1.5">
              <RadioGroupItem value="block" id={`${label}-mode-block`} />
              <Label htmlFor={`${label}-mode-block`} className="text-sm font-normal">Block selected countries</Label>
            </div>
          </RadioGroup>

          <CountryPicker
            countries={countries}
            selected={selectedCountries}
            onChange={onCountriesChange}
            buttonLabel={mode === 'allow' ? 'Add allowed countries' : 'Add blocked countries'}
          />
          {selectedCountries.length === 0 && (
            <p className="text-xs text-muted-foreground">
              {emptyHint}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

function CountryPicker({ countries, selected, onChange, buttonLabel = 'Add countries' }: {
  countries: Record<string, string>;
  selected: string[];
  onChange: (codes: string[]) => void;
  buttonLabel?: string;
}) {
  const toggle = useCallback((code: string) => {
    onChange(
      selected.includes(code)
        ? selected.filter((c) => c !== code)
        : [...selected, code],
    );
  }, [selected, onChange]);

  return (
    <div className="space-y-2">
      {selected.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selected.map((code) => (
            <Badge key={code} variant="outline" className="gap-1">
              {countries[code] ?? code}
              <button
                type="button"
                onClick={() => onChange(selected.filter((c) => c !== code))}
                className="ml-0.5 rounded-full hover:bg-muted p-0.5"
              >
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}

      <CountryDropdown
        countries={countries}
        buttonLabel={buttonLabel}
        isSelected={(code) => selected.includes(code)}
        onSelect={toggle}
      />
    </div>
  );
}

function CountryDropdown({ countries, buttonLabel, isSelected, onSelect, indicatorShape = 'checkbox', closeOnSelect = false }: {
  countries: Record<string, string>;
  buttonLabel: React.ReactNode;
  isSelected: (code: string) => boolean;
  onSelect: (code: string) => void;
  indicatorShape?: 'radio' | 'checkbox';
  closeOnSelect?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const searchRef = useRef<HTMLInputElement>(null);

  const sortedEntries = useMemo(() => {
    return Object.entries(countries).sort((a, b) => a[1].localeCompare(b[1]));
  }, [countries]);

  const filtered = useMemo(() => {
    if (!search) return sortedEntries;
    const q = search.toLowerCase();
    return sortedEntries.filter(
      ([code, name]) => name.toLowerCase().includes(q) || code.toLowerCase().includes(q),
    );
  }, [sortedEntries, search]);

  return (
    <Popover open={open} onOpenChange={(v) => { setOpen(v); if (v) setTimeout(() => searchRef.current?.focus(), 0); }}>
      <PopoverTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <Globe className="h-3.5 w-3.5" />
          {buttonLabel}
          <ChevronDown className="h-3.5 w-3.5 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-0" align="start">
        <div className="flex items-center border-b px-3">
          <SearchIcon className="h-4 w-4 shrink-0 opacity-50" />
          <input
            ref={searchRef}
            className="flex h-9 w-full bg-transparent py-2 px-2 text-sm outline-none placeholder:text-muted-foreground"
            placeholder="Search countries..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <div className="max-h-60 overflow-y-auto p-1">
          {filtered.length === 0 ? (
            <p className="px-3 py-4 text-center text-sm text-muted-foreground">No countries found</p>
          ) : (
            filtered.map(([code, name]) => {
              const selected = isSelected(code);
              return (
                <button
                  key={code}
                  type="button"
                  onClick={() => { onSelect(code); if (closeOnSelect) { setOpen(false); setSearch(''); } }}
                  className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent cursor-pointer"
                >
                  <div className={`flex h-4 w-4 shrink-0 items-center justify-center ${indicatorShape === 'radio' ? 'rounded-full' : 'rounded-sm'} border ${selected ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'}`}>
                    {selected && <Check className="h-3 w-3" />}
                  </div>
                  <span className="truncate">{name}</span>
                  <span className="ml-auto text-xs text-muted-foreground">{code}</span>
                </button>
              );
            })
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}

function NumberTypeBlockingCard({ draft, dbStatus, updateSection, onDownload, downloading }: {
  draft: PhoneRestrictionSettings;
  dbStatus: DbStatus | null;
  updateSection: <S extends keyof PhoneRestrictionSettings>(s: S, p: Partial<PhoneRestrictionSettings[S]>) => void;
  onDownload: () => void;
  downloading: boolean;
}) {
  const ntb = draft.number_type_blocking;
  const dbInstalled = dbStatus?.installed ?? false;

  return (
    <Card active={ntb.enabled}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <ShieldBan className="h-4 w-4 text-muted-foreground" />
          Number Type Blocking
        </CardTitle>
        <CardDescription>
          Block specific phone number types from registration and messaging
        </CardDescription>
        <CardAction>
          <Switch
            checked={ntb.enabled}
            onCheckedChange={(v) => updateSection('number_type_blocking', { enabled: v })}
            aria-label="Toggle number type blocking"
          />
        </CardAction>
      </CardHeader>
      {ntb.enabled && (
        <CardContent className="border-t pt-4 space-y-4">
          {!dbInstalled && (
            <div className="flex items-start gap-3 rounded-md border bg-muted/50 p-3">
              <AlertTriangle className="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
              <div className="space-y-1">
                <p className="text-sm font-medium">Enhanced database required</p>
                <p className="text-xs text-muted-foreground">
                  Download the enhanced phone database to enable number type detection.
                </p>
                <Button
                  size="sm"
                  variant="outline"
                  className="mt-1"
                  onClick={onDownload}
                  disabled={downloading}
                >
                  {downloading ? (
                    <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> Downloading...</>
                  ) : (
                    <><Download className="mr-1.5 h-3.5 w-3.5" /> Download Database</>
                  )}
                </Button>
              </div>
            </div>
          )}
          {BLOCKABLE_TYPES.map((type) => (
            <label key={type.id} className="flex items-start gap-3">
              <Checkbox
                checked={ntb.blocked_types.includes(type.id)}
                onCheckedChange={(v) =>
                  updateSection('number_type_blocking', {
                    blocked_types: toggleArrayItem(ntb.blocked_types, type.id, !!v),
                  })
                }
                className="mt-0.5"
              />
              <div>
                <span className="text-sm font-medium">{type.label}</span>
                <p className="text-xs text-muted-foreground">{type.description}</p>
              </div>
            </label>
          ))}
        </CardContent>
      )}
    </Card>
  );
}

function EnhancedDatabaseCard({ dbStatus, autoUpdate, onToggleAutoUpdate, onDownload, downloading }: {
  dbStatus: DbStatus | null;
  autoUpdate: boolean;
  onToggleAutoUpdate: (v: boolean) => void;
  onDownload: () => void;
  downloading: boolean;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Database className="h-4 w-4 text-muted-foreground" />
          Enhanced Phone Database
        </CardTitle>
        <CardDescription>
          ~300KB database enables number type detection and improved country resolution
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-md border p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium">Status</span>
            <Badge variant={dbStatus?.installed ? 'default' : 'outline'}>
              {dbStatus?.installed ? 'Installed' : 'Not Installed'}
            </Badge>
          </div>
          {dbStatus?.installed && (
            <>
              {dbStatus.version && (
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Version</span>
                  <span>{dbStatus.version}</span>
                </div>
              )}
              {dbStatus.updated_at && (
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Last updated</span>
                  <span>{new Date(dbStatus.updated_at).toLocaleDateString()}</span>
                </div>
              )}
            </>
          )}
        </div>

        <Button
          variant="outline"
          onClick={onDownload}
          disabled={downloading}
        >
          {downloading ? (
            <><Loader2 className="mr-2 h-4 w-4 animate-spin" /> Downloading...</>
          ) : dbStatus?.installed ? (
            <><RefreshCw className="mr-2 h-4 w-4" /> Re-download Latest</>
          ) : (
            <><Download className="mr-2 h-4 w-4" /> Download Database</>
          )}
        </Button>

        <Field orientation="horizontal">
          <div className="flex-1">
            <FieldLabel>Auto-update</FieldLabel>
            <FieldDescription>Automatically download the latest database weekly</FieldDescription>
          </div>
          <Switch checked={autoUpdate} onCheckedChange={onToggleAutoUpdate} />
        </Field>
      </CardContent>
    </Card>
  );
}

function PhoneCheckerCard() {
  const [phone, setPhone] = useState('');
  const [checking, setChecking] = useState(false);
  const [result, setResult] = useState<CheckResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const check = useCallback(async () => {
    if (!phone.trim()) return;
    setChecking(true);
    setResult(null);
    setError(null);
    try {
      const res = await api.post<CheckResult>('phone-restriction/check', { phone: phone.trim() });
      setResult(res);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to check phone number'));
    } finally {
      setChecking(false);
    }
  }, [phone]);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Phone className="h-4 w-4 text-muted-foreground" />
          Phone Number Checker
        </CardTitle>
        <CardDescription>
          Test a phone number against your saved restriction settings
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <Field className="max-w-md">
          <FieldLabel>Phone number</FieldLabel>
          <div className="flex gap-2">
            <Input
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="+12025551234"
              className="flex-1"
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); check(); } }}
            />
            <Button onClick={check} disabled={!phone.trim() || checking}>
              {checking ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Check'}
            </Button>
          </div>
          <FieldDescription>Use E.164 format (e.g. +12025551234)</FieldDescription>
        </Field>
        {error && (
          <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            {error}
          </div>
        )}

        {result && (
          <div className="rounded-md border bg-muted/50 p-3 space-y-2">
            <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm max-w-md">
              <span className="text-muted-foreground">Country</span>
              <span>{result.country_name ? `${result.country_name} (${result.country})` : result.country ?? 'Unknown'}</span>
              <span className="text-muted-foreground">Number type</span>
              <span>{result.number_type ?? 'Unknown'}</span>
              {(['auth', 'messaging'] as const).map((ctx) => (
                <Fragment key={ctx}>
                  <span className="text-muted-foreground capitalize">{ctx}</span>
                  <span className="flex items-center gap-2">
                    {result[ctx].allowed ? (
                      <Badge className="bg-primary hover:bg-primary">Allowed</Badge>
                    ) : (
                      <Badge variant="destructive">Blocked</Badge>
                    )}
                    {!result[ctx].allowed && result[ctx].reason && (
                      <span className="text-muted-foreground">{result[ctx].reason}</span>
                    )}
                  </span>
                </Fragment>
              ))}
            </div>
            <p className="text-xs text-muted-foreground mt-1">Results reflect saved settings</p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
