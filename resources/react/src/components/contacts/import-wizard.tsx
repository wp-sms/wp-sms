import { __, sprintf } from '@wordpress/i18n';
import { useState, useRef } from 'react';
import type { ImportPreview, ImportResult } from '@/lib/api';
import { onActivate } from '@/lib/utils';
import { useConfirm } from '@/components/confirm-provider';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription, DrawerFooter } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { MATCH_FIELD_OPTIONS, DUPLICATE_HANDLING_OPTIONS, ATTRIBUTE_FIELDS } from '@/lib/constants';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Upload, CheckCircle, AlertCircle } from 'lucide-react';

type Step = 'upload' | 'map' | 'options' | 'preview' | 'results';

const CONTACT_FIELDS = [
  { value: '', label: __('Skip', 'wp-sms') },
  ...ATTRIBUTE_FIELDS,
  { value: 'custom', label: __('Custom field...', 'wp-sms') },
];

interface ImportWizardProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onPreview: (file: File) => Promise<ImportPreview>;
  onImport: (file: File, options: { mapping: Record<string, string>; matchField: string; duplicateHandling: string }) => Promise<ImportResult>;
}

export function ImportWizard({ open, onOpenChange, onPreview, onImport }: ImportWizardProps) {
  const confirm = useConfirm();
  const [step, setStep] = useState<Step>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [matchField, setMatchField] = useState('email');
  const [duplicateHandling, setDuplicateHandling] = useState('update');
  const [result, setResult] = useState<ImportResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const reset = () => {
    setStep('upload');
    setFile(null);
    setPreview(null);
    setMapping({});
    setMatchField('email');
    setDuplicateHandling('update');
    setResult(null);
    setUploadError(null);
  };

  const handleFileSelect = async (selectedFile: File) => {
    setUploadError(null);
    setFile(selectedFile);
    setLoading(true);
    try {
      const p = await onPreview(selectedFile);
      if (p.rows.length === 0) {
        setUploadError(__('CSV file has no data rows.', 'wp-sms'));
        setFile(null);
        setLoading(false);
        return;
      }
      setPreview(p);
      // Auto-map headers
      const autoMap: Record<string, string> = {};
      for (const header of p.headers) {
        const lower = header.toLowerCase().replace(/\s+/g, '_');
        const match = ATTRIBUTE_FIELDS.find((f) => f.value === lower || f.label.toLowerCase() === header.toLowerCase());
        if (match) autoMap[header] = match.value;
        else autoMap[header] = '';
      }
      setMapping(autoMap);
      setStep('map');
    } catch {
      setFile(null);
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (!file) return;
    setLoading(true);
    try {
      // Build field mapping: contact_field -> csv_header
      const fieldMapping: Record<string, string> = {};
      for (const [csvHeader, contactField] of Object.entries(mapping)) {
        if (contactField) fieldMapping[contactField] = csvHeader;
      }
      const r = await onImport(file, { mapping: fieldMapping, matchField, duplicateHandling });
      setResult(r);
      setStep('results');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Drawer open={open} onOpenChange={async (o) => {
      if (!o && step !== 'upload' && step !== 'results') {
        const ok = await confirm({
          title: __('Close import wizard?', 'wp-sms'),
          description: __('Your import progress will be lost.', 'wp-sms'),
          confirmLabel: __('Close', 'wp-sms'),
          variant: 'destructive',
        });
        if (!ok) return;
      }
      onOpenChange(o);
      if (!o) reset();
    }}>
      <DrawerContent className="sm:max-w-lg overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle>{__('Import Contacts', 'wp-sms')}</DrawerTitle>
          <DrawerDescription>
            {step === 'upload' && __('Upload a CSV file to import contacts.', 'wp-sms')}
            {step === 'map' && __('Map CSV columns to contact fields.', 'wp-sms')}
            {step === 'options' && __('Configure import options.', 'wp-sms')}
            {step === 'preview' && __('Review mapped data before importing.', 'wp-sms')}
            {step === 'results' && __('Import complete.', 'wp-sms')}
          </DrawerDescription>
        </DrawerHeader>

        <div className="px-4 space-y-4">
          {/* Step: Upload */}
          {step === 'upload' && (
            <div
              role="button"
              tabIndex={0}
              aria-label={__('Upload CSV file', 'wp-sms')}
              className="flex flex-col items-center justify-center py-12 border-2 border-dashed rounded-lg cursor-pointer hover:border-primary/50 transition-colors"
              onClick={() => fileRef.current?.click()}
              onKeyDown={onActivate(() => fileRef.current?.click())}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => {
                e.preventDefault();
                const f = e.dataTransfer.files[0];
                if (f?.name.endsWith('.csv')) void handleFileSelect(f);
              }}
            >
              <Upload className="h-8 w-8 text-muted-foreground mb-3" />
              <p className="text-sm font-medium">{__('Drop CSV file here or click to browse', 'wp-sms')}</p>
              <p className="text-xs text-muted-foreground mt-1">{__('Only .csv files are supported', 'wp-sms')}</p>
              {uploadError && (
                <div className="flex items-center gap-1.5 mt-2 text-sm text-destructive">
                  <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                  {uploadError}
                </div>
              )}
              <input
                ref={fileRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => {
                  const f = e.target.files?.[0];
                  if (f && f.name.endsWith('.csv')) void handleFileSelect(f);
                }}
              />
            </div>
          )}

          {/* Step: Map */}
          {step === 'map' && preview && (
            <div className="space-y-3">
              {preview.headers.map((header) => (
                <div key={header} className="flex items-center gap-3">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{header}</p>
                    <p className="text-xs text-muted-foreground truncate">
                      {preview.rows[0]?.[preview.headers.indexOf(header)] ?? ''}
                    </p>
                  </div>
                  <Select value={mapping[header] || ''} onValueChange={(v) => setMapping({ ...mapping, [header]: v })}>
                    <SelectTrigger className="w-44 h-8 text-sm">
                      <SelectValue placeholder={__('Skip', 'wp-sms')} />
                    </SelectTrigger>
                    <SelectContent>
                      {CONTACT_FIELDS.map((f) => (
                        <SelectItem key={f.value} value={f.value || 'skip'}>{f.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              ))}
              {Object.values(mapping).includes('phone') && (
                <p className="text-xs text-muted-foreground">
                  {__('Phone numbers must include country code (e.g. +12025551234). Rows with invalid phone numbers will be skipped.', 'wp-sms')}
                </p>
              )}
            </div>
          )}

          {/* Step: Options */}
          {step === 'options' && (
            <div className="space-y-6">
              <div>
                <p className="text-sm font-medium mb-2">{__('Match existing contacts by', 'wp-sms')}</p>
                <RadioGroup value={matchField} onValueChange={setMatchField}>
                  {MATCH_FIELD_OPTIONS.map((opt) => (
                    <div key={opt.value} className="flex items-center gap-2">
                      <RadioGroupItem value={opt.value} id={`mf-${opt.value}`} />
                      <Label htmlFor={`mf-${opt.value}`} className="text-sm">{opt.label}</Label>
                    </div>
                  ))}
                </RadioGroup>
              </div>
              <div>
                <p className="text-sm font-medium mb-2">{__('When a match is found', 'wp-sms')}</p>
                <RadioGroup value={duplicateHandling} onValueChange={setDuplicateHandling}>
                  {DUPLICATE_HANDLING_OPTIONS.map((opt) => (
                    <div key={opt.value} className="flex items-center gap-2">
                      <RadioGroupItem value={opt.value} id={`dh-${opt.value}`} />
                      <Label htmlFor={`dh-${opt.value}`} className="text-sm">{opt.label}</Label>
                    </div>
                  ))}
                </RadioGroup>
              </div>
            </div>
          )}

          {/* Step: Preview */}
          {step === 'preview' && preview && (
            <div className="space-y-3">
              <p className="text-sm">
                {sprintf(__('Ready to import %s+ rows', 'wp-sms'), preview.rows.length)}
              </p>
              <div className="rounded-lg border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      {preview.headers.map((h) => mapping[h] && mapping[h] !== 'skip' ? (
                        <TableHead key={h} className="text-xs whitespace-nowrap">{mapping[h]}</TableHead>
                      ) : null)}
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {preview.rows.slice(0, 5).map((row, i) => (
                      <TableRow key={i}>
                        {preview.headers.map((h, j) => mapping[h] && mapping[h] !== 'skip' ? (
                          <TableCell key={j} className="text-xs whitespace-nowrap">{row[j]}</TableCell>
                        ) : null)}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>
          )}

          {/* Step: Results */}
          {step === 'results' && result && (
            <div className="space-y-4">
              <Alert variant="success">
                <CheckCircle className="h-4 w-4" />
                <AlertTitle>{__('Import complete', 'wp-sms')}</AlertTitle>
                <AlertDescription>
                  {sprintf(__('%1$d created, %2$d updated, %3$d skipped', 'wp-sms'), result.imported, result.updated, result.skipped)}
                </AlertDescription>
              </Alert>
              {result.errors.length > 0 && (
                <div className="space-y-1">
                  <div className="flex items-center gap-2 text-amber-700">
                    <AlertCircle className="h-4 w-4" />
                    <span className="text-sm font-medium">{sprintf(__('%d errors', 'wp-sms'), result.errors.length)}</span>
                  </div>
                  <div className="max-h-40 overflow-y-auto rounded-lg border p-2 text-xs space-y-1">
                    {result.errors.map((err, i) => (
                      <p key={i} className="text-muted-foreground">{err}</p>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        <DrawerFooter>
          {step === 'map' && (
            <>
              <Button variant="outline" onClick={() => setStep('upload')}>{__('Back', 'wp-sms')}</Button>
              <Button onClick={() => setStep('options')}>{__('Next', 'wp-sms')}</Button>
            </>
          )}
          {step === 'options' && (
            <>
              <Button variant="outline" onClick={() => setStep('map')}>{__('Back', 'wp-sms')}</Button>
              <Button onClick={() => setStep('preview')}>{__('Next', 'wp-sms')}</Button>
            </>
          )}
          {step === 'preview' && (
            <>
              <Button variant="outline" onClick={() => setStep('options')}>{__('Back', 'wp-sms')}</Button>
              <Button onClick={handleImport} disabled={loading}>
                {loading ? __('Importing...', 'wp-sms') : __('Import', 'wp-sms')}
              </Button>
            </>
          )}
          {step === 'results' && (
            <Button onClick={() => { onOpenChange(false); reset(); }}>{__('Done', 'wp-sms')}</Button>
          )}
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
