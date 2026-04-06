import { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { PageHeader } from '@/components/layout/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Progress } from '@/components/ui/progress';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useConfirm } from '@/components/confirm-provider';
import { toast } from 'sonner';
import {
  ArrowRight,
  ArrowLeft,
  ArrowRightLeft,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Info,
  Download,
  Pause,
  Play,
  RotateCcw,
  Smartphone,
  Key,
  Settings,
  Loader2,
  Shield,
  Sparkles,
} from 'lucide-react';
import { getConfig } from '@/lib/api';
import {
  useMigration,
  type DetectionResult,
  type PreviewStep,
  type MigrationStatus,
  type PreflightCheck,
} from '@/hooks/use-migration';

type WizardStep = 'detection' | 'preview' | 'configure' | 'progress' | 'results';

export function MigrationPage({ embedded }: { embedded?: boolean } = {}) {
  const migration = useMigration();
  const confirm = useConfirm();

  const [step, setStep] = useState<WizardStep>('detection');
  const [selectedMigrator, setSelectedMigrator] = useState<string | null>(null);
  const [conflictResolution, setConflictResolution] = useState('skip');
  const [defaultCountry, setDefaultCountry] = useState('');
  const [wcSync, setWcSync] = useState(false);
  const [dryRun, setDryRun] = useState(false);

  // Sync wizard step when migration status loads or changes.
  const initialSyncDone = useRef(false);
  useEffect(() => {
    if (migration.loading || !migration.status) return;
    // Only auto-sync on initial load — don't override user navigation later.
    if (initialSyncDone.current) return;
    initialSyncDone.current = true;

    const s = migration.status.status;
    if (s === 'running' || s === 'paused') setStep('progress');
    else if (s === 'completed' || s === 'failed') setStep('results');

    if (migration.status.migrator_id) setSelectedMigrator(migration.status.migrator_id);
  }, [migration.loading, migration.status]);

  if (migration.loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {!embedded && (
        <PageHeader
          icon={ArrowRightLeft}
          title={__('Migration', 'wp-sms')}
          metadata={__('Import data from other plugins. Your original data is never modified or deleted.', 'wp-sms')}
        />
      )}

      {step === 'detection' && (
        <DetectionStep
          available={migration.available}
          onSelect={(id) => {
            setSelectedMigrator(id);
            void migration.fetchPreview(id);
            void migration.fetchPreflight(id);
            setStep('preview');
          }}
          status={migration.status}
          onViewResults={() => setStep('results')}
          onViewProgress={() => setStep('progress')}
        />
      )}

      {step === 'preview' && selectedMigrator && (
        <PreviewStep
          preview={migration.preview}
          preflight={migration.preflight}
          loading={migration.actionLoading}
          onBack={() => setStep('detection')}
          onContinue={() => setStep('configure')}
        />
      )}

      {step === 'configure' && selectedMigrator && (
        <ConfigureStep
          preview={migration.preview}
          conflictResolution={conflictResolution}
          onConflictResolutionChange={setConflictResolution}
          defaultCountry={defaultCountry}
          onDefaultCountryChange={setDefaultCountry}
          wcSync={wcSync}
          onWcSyncChange={setWcSync}
          dryRun={dryRun}
          onDryRunChange={setDryRun}
          loading={migration.actionLoading}
          onBack={() => setStep('preview')}
          onStart={async () => {
            try {
              await migration.start(selectedMigrator, {
                conflict_resolution: conflictResolution,
                default_country: defaultCountry || undefined,
                wc_sync: wcSync,
                dry_run: dryRun,
              });
              setStep('progress');
              toast.success(__('Migration started!', 'wp-sms'));
            } catch {
              toast.error(__('Failed to start migration.', 'wp-sms'));
            }
          }}
        />
      )}

      {step === 'progress' && (
        <ProgressStep
          status={migration.status}
          onPause={async () => {
            await migration.pause();
            toast.success(__('Migration paused.', 'wp-sms'));
          }}
          onResume={async () => {
            await migration.resume();
            toast.success(__('Migration resumed.', 'wp-sms'));
          }}
          onViewResults={() => {
            void migration.fetchErrors();
            setStep('results');
          }}
          actionLoading={migration.actionLoading}
        />
      )}

      {step === 'results' && selectedMigrator && (
        <ResultsStep
          status={migration.status}
          errors={migration.errors}
          onFetchErrors={migration.fetchErrors}
          onRollback={async () => {
            const ok = await confirm({
              title: __('Roll back import?', 'wp-sms'),
              description: __('This will undo the imported data and restore your previous WSMS settings. Your Digits data is never affected.', 'wp-sms'),
              confirmLabel: __('Roll Back', 'wp-sms'),
              variant: 'destructive',
            });
            if (ok) {
              await migration.rollback();
              toast.success(__('Import rolled back.', 'wp-sms'));
              setStep('detection');
            }
          }}
          onSwitchover={async () => {
            const ok = await confirm({
              title: __('Activate WSMS Authentication?', 'wp-sms'),
              description: __('This will deactivate Digits and enable WSMS authentication. Your users will sign in through WSMS from this point forward. Already logged-in users are not affected. Your Digits data is never deleted. You can reactivate Digits and roll back at any time.', 'wp-sms'),
              confirmLabel: __('Activate WSMS Auth', 'wp-sms'),
            });
            if (ok) {
              try {
                const result = await migration.switchover(selectedMigrator);
                toast.success(result?.message || __('WSMS authentication activated!', 'wp-sms'));
              } catch {
                toast.error(__('Switchover failed.', 'wp-sms'));
              }
            }
          }}
          actionLoading={migration.actionLoading}
        />
      )}
    </div>
  );
}

// --- Sub-components ---

function DetectionStep({ available, onSelect, status, onViewResults, onViewProgress }: {
  available: DetectionResult[];
  onSelect: (id: string) => void;
  status: MigrationStatus | null;
  onViewResults: () => void;
  onViewProgress: () => void;
}) {
  // If there's an active migration, show its status.
  if (status) {
    if (status.status === 'running' || status.status === 'paused') {
      return (
        <Card>
          <CardHeader>
            <CardTitle>{__('Migration in progress', 'wp-sms')}</CardTitle>
            <CardDescription>
              {status.status === 'paused' ? __('Migration is paused.', 'wp-sms') : __('Migration is running in the background.', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Progress value={status.percent} className="mb-4" />
            <p className="text-sm text-muted-foreground mb-4">
              {sprintf(__('%1$s%% — %2$s of %3$s items', 'wp-sms'), status.percent, status.processed_items.toLocaleString(), status.total_items.toLocaleString())}
            </p>
            <Button onClick={onViewProgress}>{__('View Details', 'wp-sms')}</Button>
          </CardContent>
        </Card>
      );
    }

    if (status.status === 'completed') {
      return (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CheckCircle2 className="size-5 text-green-600" />
              {__('Import complete', 'wp-sms')}
            </CardTitle>
            <CardDescription>{__('Your data has been imported. Review the results and activate WSMS authentication.', 'wp-sms')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={onViewResults}>{__('Review Results', 'wp-sms')}</Button>
          </CardContent>
        </Card>
      );
    }
  }

  if (available.length === 0) {
    return (
      <Card>
        <CardContent className="py-12 text-center text-muted-foreground">
          <Info className="size-8 mx-auto mb-3 opacity-50" />
          <p>{__('No plugins with importable data were detected.', 'wp-sms')}</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold">{__('Detected plugins', 'wp-sms')}</h3>
      {available.map((plugin) => (
        <Card key={plugin.id} className="cursor-pointer hover:border-primary/50 transition-colors" onClick={() => onSelect(plugin.id)}>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  {plugin.id === 'digits' ? 'Digits' : plugin.id}
                  {plugin.active && <Badge variant="secondary">{__('Active', 'wp-sms')}</Badge>}
                  {/* eslint-disable-next-line react/jsx-no-literals */}
                  {plugin.version && <Badge variant="outline">{'v'}{plugin.version}</Badge>}
                </CardTitle>
                <CardDescription className="mt-1">
                  {plugin.summary.join(' · ')}
                </CardDescription>
              </div>
              <Button variant="default" size="sm">
                {__('Preview Import', 'wp-sms')}
                <ArrowRight className="size-4 ms-1 rtl:scale-x-[-1]" />
              </Button>
            </div>
          </CardHeader>
        </Card>
      ))}
    </div>
  );
}

function PreviewStep({ preview, preflight, loading, onBack, onContinue }: {
  preview: Record<string, PreviewStep> | null;
  preflight: { checks: Record<string, PreflightCheck>; can_start: boolean } | null;
  loading: boolean;
  onBack: () => void;
  onContinue: () => void;
}) {
  if (loading || !preview) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  const stepIcons: Record<string, typeof Smartphone> = {
    user_phones: Smartphone,
    totp_enrollment: Key,
    settings: Settings,
  };

  const hasBlockingChecks = preflight && !preflight.can_start;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="size-4 me-1 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
        <h3 className="text-lg font-semibold">{__('Import preview', 'wp-sms')}</h3>
      </div>

      {/* Preflight checks */}
      {preflight && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{__('Pre-flight checks', 'wp-sms')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {Object.entries(preflight.checks).map(([key, check]) => (
              <div key={key} className="flex items-start gap-3">
                {check.status === 'pass' && <CheckCircle2 className="size-5 text-green-600 mt-0.5" />}
                {check.status === 'warning' && <AlertTriangle className="size-5 text-yellow-600 mt-0.5" />}
                {check.status === 'fail' && <AlertCircle className="size-5 text-red-600 mt-0.5" />}
                <div>
                  <p className="text-sm font-medium">{check.label}</p>
                  <p className="text-sm text-muted-foreground">{check.detail}</p>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* Per-step preview */}
      {Object.entries(preview).map(([stepId, stepData]) => {
        const Icon = stepIcons[stepId] || Info;
        return (
          <Card key={stepId}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon className="size-5" />
                {stepData.label}
                <Badge variant="outline">{stepData.total_items.toLocaleString()} {__('items', 'wp-sms')}</Badge>
              </CardTitle>
              <CardDescription>{stepData.description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div>
                  <p className="text-muted-foreground">{__('Ready to import', 'wp-sms')}</p>
                  <p className="text-lg font-semibold text-green-600">{stepData.ready_to_migrate.toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">{__('Conflicts', 'wp-sms')}</p>
                  <p className="text-lg font-semibold text-yellow-600">{stepData.conflicts.toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">{__('Invalid', 'wp-sms')}</p>
                  <p className="text-lg font-semibold text-red-600">{stepData.invalid_data.toLocaleString()}</p>
                </div>
              </div>

              {stepData.warnings.map((warning, i) => (
                <Alert key={i} variant="default">
                  <AlertTriangle className="size-4" />
                  <AlertDescription>{warning}</AlertDescription>
                </Alert>
              ))}
            </CardContent>
          </Card>
        );
      })}

      {/* What won't carry over */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <Info className="size-5 text-amber-500" />
            {__('What won\'t carry over', 'wp-sms')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ul className="space-y-2 text-sm text-muted-foreground">
            <li>{__('Login history — Fresh start in WSMS monitoring.', 'wp-sms')}</li>
            <li>{__('Security keys (WebAuthn) — Users will need to re-register them.', 'wp-sms')}</li>
            <li>{__('Custom form fields — Recreate them in Profile Fields settings.', 'wp-sms')}</li>
            <li>{__('WhatsApp OTP configuration — Set up separately if needed.', 'wp-sms')}</li>
            <li>{__('Custom CSS/branding — Use WSMS Branding settings instead.', 'wp-sms')}</li>
            <li>{__('SMS gateway credentials — Set up your gateway on the Gateways page.', 'wp-sms')}</li>
          </ul>
        </CardContent>
      </Card>

      <div className="flex justify-end gap-3">
        <Button variant="outline" onClick={onBack}>{__('Back', 'wp-sms')}</Button>
        <Button onClick={onContinue} disabled={!!hasBlockingChecks}>
          {__('Configure Import', 'wp-sms')}
          <ArrowRight className="size-4 ms-1 rtl:scale-x-[-1]" />
        </Button>
      </div>
    </div>
  );
}

function ConfigureStep({ preview, conflictResolution, onConflictResolutionChange, defaultCountry, onDefaultCountryChange, wcSync, onWcSyncChange, dryRun, onDryRunChange, loading, onBack, onStart }: {
  preview: Record<string, PreviewStep> | null;
  conflictResolution: string;
  onConflictResolutionChange: (v: string) => void;
  defaultCountry: string;
  onDefaultCountryChange: (v: string) => void;
  wcSync: boolean;
  onWcSyncChange: (v: boolean) => void;
  dryRun: boolean;
  onDryRunChange: (v: boolean) => void;
  loading: boolean;
  onBack: () => void;
  onStart: () => void;
}) {
  const phoneConflicts = preview?.user_phones?.conflicts ?? 0;
  const hasInvalidPhones = (preview?.user_phones?.invalid_data ?? 0) > 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="size-4 me-1 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
        <h3 className="text-lg font-semibold">{__('Configure import', 'wp-sms')}</h3>
      </div>

      {/* Conflict resolution */}
      {phoneConflicts > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{__('When a user already has a different phone number in WSMS', 'wp-sms')}</CardTitle>
            <CardDescription>
              {phoneConflicts.toLocaleString()} {__('users have a phone in both systems.', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Select value={conflictResolution} onValueChange={onConflictResolutionChange}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="skip">{__('Keep WSMS phone (recommended)', 'wp-sms')}</SelectItem>
                <SelectItem value="overwrite">{__('Use Digits phone (backup existing)', 'wp-sms')}</SelectItem>
                <SelectItem value="keep_existing">{__('Flag for review', 'wp-sms')}</SelectItem>
              </SelectContent>
            </Select>
          </CardContent>
        </Card>
      )}

      {/* Default country code */}
      {hasInvalidPhones && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{__('Default country code', 'wp-sms')}</CardTitle>
            <CardDescription>
              {__('Some phone numbers are missing a country code. Select the country most of your users are in.', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Select value={defaultCountry} onValueChange={onDefaultCountryChange}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={__('Select country...', 'wp-sms')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="+1">{__('United States (+1)', 'wp-sms')}</SelectItem>
                <SelectItem value="+44">{__('United Kingdom (+44)', 'wp-sms')}</SelectItem>
                <SelectItem value="+49">{__('Germany (+49)', 'wp-sms')}</SelectItem>
                <SelectItem value="+33">{__('France (+33)', 'wp-sms')}</SelectItem>
                <SelectItem value="+91">{__('India (+91)', 'wp-sms')}</SelectItem>
                <SelectItem value="+61">{__('Australia (+61)', 'wp-sms')}</SelectItem>
                <SelectItem value="+81">{__('Japan (+81)', 'wp-sms')}</SelectItem>
                <SelectItem value="+55">{__('Brazil (+55)', 'wp-sms')}</SelectItem>
                <SelectItem value="+90">{__('Turkey (+90)', 'wp-sms')}</SelectItem>
                <SelectItem value="+971">{__('UAE (+971)', 'wp-sms')}</SelectItem>
              </SelectContent>
            </Select>
          </CardContent>
        </Card>
      )}

      {/* WooCommerce sync */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{__('WooCommerce billing phone', 'wp-sms')}</CardTitle>
          <CardDescription>{__('Also update WooCommerce billing phone numbers to match the imported phone.', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-3">
            <Switch checked={wcSync} onCheckedChange={onWcSyncChange} id="wc-sync" />
            <Label htmlFor="wc-sync">{__('Sync billing phone', 'wp-sms')}</Label>
          </div>
        </CardContent>
      </Card>

      {/* Test mode */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{__('Test before importing', 'wp-sms')}</CardTitle>
          <CardDescription>{__('Process all data without writing any changes. Shows exact counts.', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-3">
            <Switch checked={dryRun} onCheckedChange={onDryRunChange} id="dry-run" />
            <Label htmlFor="dry-run">{__('Full dry run', 'wp-sms')}</Label>
          </div>
        </CardContent>
      </Card>

      <Separator />

      <div className="flex justify-end gap-3">
        <Button variant="outline" onClick={onBack}>{__('Back', 'wp-sms')}</Button>
        <Button onClick={onStart} disabled={loading}>
          {loading && <Loader2 className="size-4 me-1 animate-spin" />}
          {dryRun ? __('Start Dry Run', 'wp-sms') : __('Start Import', 'wp-sms')}
        </Button>
      </div>
    </div>
  );
}

function ProgressStep({ status, onPause, onResume, onViewResults, actionLoading }: {
  status: MigrationStatus | null;
  onPause: () => void;
  onResume: () => void;
  onViewResults: () => void;
  actionLoading: boolean;
}) {
  if (!status) return null;

  const isPaused = status.status === 'paused';
  const isCompleted = status.status === 'completed';
  const isFailed = status.status === 'failed';

  const stepLabels: Record<string, string> = {
    user_phones: __('Phone Numbers', 'wp-sms'),
    totp_enrollment: __('Authenticator Apps', 'wp-sms'),
    settings: __('Settings', 'wp-sms'),
  };

  return (
    <div className="space-y-6">
      <h3 className="text-lg font-semibold">
        {isCompleted ? __('Import complete', 'wp-sms') : isFailed ? __('Import failed', 'wp-sms') : __('Importing...', 'wp-sms')}
      </h3>

      {/* Overall progress */}
      <Card>
        <CardContent className="pt-6">
          <Progress value={status.percent} className="mb-2" />
          <p className="text-sm text-muted-foreground">
            {sprintf(__('%1$s%% — %2$s of %3$s items', 'wp-sms'), status.percent, status.processed_items.toLocaleString(), status.total_items.toLocaleString())}
          </p>
        </CardContent>
      </Card>

      {/* Per-step progress */}
      <div className="space-y-3">
        {Object.entries(status.steps).map(([stepId, step]) => {
          const percent = step.total_items > 0 ? Math.round((step.processed_items / step.total_items) * 100) : 0;
          return (
            <Card key={stepId}>
              <CardContent className="py-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium">{stepLabels[stepId] || stepId}</span>
                  <div className="flex items-center gap-2">
                    {step.status === 'completed' && <CheckCircle2 className="size-4 text-green-600" />}
                    {step.status === 'running' && <Loader2 className="size-4 text-blue-600 animate-spin" />}
                    {step.status === 'failed' && <AlertCircle className="size-4 text-red-600" />}
                    {step.status === 'pending' && <span className="text-xs text-muted-foreground">{__('Waiting', 'wp-sms')}</span>}
                    <span className="text-xs text-muted-foreground">{percent}%</span>
                  </div>
                </div>
                {step.total_items > 0 && (
                  <Progress value={percent} className="h-1.5" />
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>

      <p className="text-sm text-muted-foreground">
        {__('Running in the background. Feel free to navigate away — we\'ll keep you updated.', 'wp-sms')}
      </p>

      <div className="flex gap-3">
        {!isCompleted && !isFailed && (
          isPaused ? (
            <Button onClick={onResume} disabled={actionLoading}>
              <Play className="size-4 me-1" />
              {__('Resume', 'wp-sms')}
            </Button>
          ) : (
            <Button variant="outline" onClick={onPause} disabled={actionLoading}>
              <Pause className="size-4 me-1" />
              {__('Pause', 'wp-sms')}
            </Button>
          )
        )}
        {(isCompleted || isFailed) && (
          <Button onClick={onViewResults}>
            {__('View Results', 'wp-sms')}
            <ArrowRight className="size-4 ms-1 rtl:scale-x-[-1]" />
          </Button>
        )}
      </div>
    </div>
  );
}

function ResultsStep({ status, errors, onFetchErrors, onRollback, onSwitchover, actionLoading }: {
  status: MigrationStatus | null;
  errors: Array<{ step: string; user_id?: number; phone?: string; reason: string }>;
  onFetchErrors: () => void;
  onRollback: () => void;
  onSwitchover: () => void;
  actionLoading: boolean;
}) {
  if (!status) return null;

  const totalMigrated = Object.values(status.steps).reduce((sum, s) => sum + s.migrated_count, 0);
  const totalSkipped = Object.values(status.steps).reduce((sum, s) => sum + s.skipped_count, 0);
  const totalFailed = Object.values(status.steps).reduce((sum, s) => sum + s.failed_count, 0);

  return (
    <div className="space-y-6">
      <h3 className="text-lg font-semibold">{__('Import results', 'wp-sms')}</h3>

      {/* Summary cards */}
      <div className="grid grid-cols-3 gap-4">
        <Card>
          <CardContent className="pt-6 text-center">
            <CheckCircle2 className="size-8 mx-auto mb-2 text-green-600" />
            <p className="text-2xl font-bold text-green-600">{totalMigrated.toLocaleString()}</p>
            <p className="text-sm text-muted-foreground">{__('Imported', 'wp-sms')}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6 text-center">
            <AlertTriangle className="size-8 mx-auto mb-2 text-yellow-600" />
            <p className="text-2xl font-bold text-yellow-600">{totalSkipped.toLocaleString()}</p>
            <p className="text-sm text-muted-foreground">{__('Skipped', 'wp-sms')}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6 text-center">
            <AlertCircle className="size-8 mx-auto mb-2 text-red-600" />
            <p className="text-2xl font-bold text-red-600">{totalFailed.toLocaleString()}</p>
            <p className="text-sm text-muted-foreground">{__('Errors', 'wp-sms')}</p>
          </CardContent>
        </Card>
      </div>

      {/* Errors section */}
      {totalFailed > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{__('Error details', 'wp-sms')}</CardTitle>
          </CardHeader>
          <CardContent>
            {errors.length === 0 ? (
              <Button variant="outline" size="sm" onClick={onFetchErrors}>
                <Download className="size-4 me-1" />
                {__('Load Error Details', 'wp-sms')}
              </Button>
            ) : (
              <div className="space-y-2 max-h-64 overflow-y-auto">
                {errors.slice(0, 50).map((err, i) => (
                  <div key={i} className="flex items-start gap-2 text-sm">
                    <AlertCircle className="size-4 text-red-500 mt-0.5 shrink-0" />
                    <span>
                      {err.user_id && <span className="font-mono text-xs text-muted-foreground me-2">#{err.user_id}</span>}
                      {err.phone && <span className="font-mono text-xs text-muted-foreground me-2">{err.phone}</span>}
                      {err.reason}
                    </span>
                  </div>
                ))}
                {errors.length > 50 && (
                  <p className="text-sm text-muted-foreground">
                    {__('...and', 'wp-sms')} {(errors.length - 50).toLocaleString()} {__('more', 'wp-sms')}
                  </p>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      <Separator />

      {/* Switchover section */}
      {status.status === 'completed' && (
        <Card className="border-primary/30">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Shield className="size-5 text-primary" />
              {__('Ready to switch?', 'wp-sms')}
            </CardTitle>
            <CardDescription>
              {__('Activate WSMS authentication to replace Digits. We recommend doing this during your site\'s quietest hours.', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex gap-3">
            <Button onClick={onSwitchover} disabled={actionLoading}>
              {actionLoading && <Loader2 className="size-4 me-1 animate-spin" />}
              {__('Activate WSMS Auth', 'wp-sms')}
            </Button>
            <Button variant="outline" onClick={onRollback} disabled={actionLoading}>
              <RotateCcw className="size-4 me-1" />
              {__('Roll Back Import', 'wp-sms')}
            </Button>
          </CardContent>
        </Card>
      )}

      {status.status === 'failed' && (
        <div className="flex gap-3">
          <Button variant="outline" onClick={onRollback} disabled={actionLoading}>
            <RotateCcw className="size-4 me-1" />
            {__('Roll Back Import', 'wp-sms')}
          </Button>
        </div>
      )}

      <OnboardingContinueCard />
    </div>
  );
}

function OnboardingContinueCard() {
  const onboardingState = getConfig().onboarding;
  if (!onboardingState || onboardingState.status === 'completed') return null;

  return (
    <Card className="border-dashed">
      <CardContent className="flex items-center justify-between py-4">
        <div className="flex items-center gap-2">
          <Sparkles className="size-4 text-primary" />
          <span className="text-sm font-medium">{__('Continue setting up WSMS', 'wp-sms')}</span>
        </div>
        <Button variant="outline" size="sm" onClick={() => { window.location.hash = 'onboarding'; }}>
          {__('Continue Setup', 'wp-sms')}
          <ArrowRight className="size-3.5 ms-1 rtl:scale-x-[-1]" />
        </Button>
      </CardContent>
    </Card>
  );
}
