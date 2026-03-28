import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CONTACT_STATUSES } from '@/lib/constants';
import { Download } from 'lucide-react';
import { toast } from 'sonner';

interface ExportDialogProps {
  children: React.ReactNode;
  onExport: (filters?: { status?: string }) => Promise<{ url: string; filename: string }>;
}

export function ExportDialog({ children, onExport }: ExportDialogProps) {
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState('all');
  const [loading, setLoading] = useState(false);

  const handleExport = async () => {
    setLoading(true);
    try {
      const filters = status !== 'all' ? { status } : undefined;
      const { url, filename } = await onExport(filters);

      // Fetch blob and trigger download
      const res = await fetch(url, { credentials: 'same-origin' });
      const blob = await res.blob();
      const objectUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = objectUrl;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(objectUrl);

      setOpen(false);
    } catch {
      toast.error(__('Export failed.', 'wp-sms'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>{children}</PopoverTrigger>
      <PopoverContent className="w-64 p-4" align="end">
        <div className="space-y-3">
          <p className="text-sm font-medium">{__('Export Contacts', 'wp-sms')}</p>
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All statuses', 'wp-sms')}</SelectItem>
              {CONTACT_STATUSES.map((s) => (
                <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button className="w-full" onClick={handleExport} disabled={loading}>
            <Download className="me-1.5 h-3.5 w-3.5" />
            {loading ? 'Exporting...' : 'Download CSV'}
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}
