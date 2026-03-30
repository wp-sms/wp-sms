import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { api, type HealthCheck, type TableSizeEntry } from '@/lib/api';
import { fmtNumber } from '@/lib/utils';
import { HealthCheckRow } from './health-check-row';
import { toast } from 'sonner';

interface DataSecuritySectionProps {
  checks: HealthCheck[];
}

export function DataSecuritySection({ checks }: DataSecuritySectionProps) {
  const [tableSizes, setTableSizes] = useState<TableSizeEntry[] | null>(null);
  const [tableSizeLoading, setTableSizeLoading] = useState(false);

  const loadTableSizes = async () => {
    setTableSizeLoading(true);
    try {
      const res = await api.get<{ success: boolean; data: TableSizeEntry[] }>('system/health/table-sizes');
      setTableSizes(res.data);
    } catch {
      toast.error(__('Failed to load table sizes.', 'wp-sms'));
    } finally {
      setTableSizeLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        {checks.map((check, i) => (
          <HealthCheckRow
            key={check.id}
            check={check}
            index={i}
            onLazyLoad={check.id === 'data_table_sizes' ? loadTableSizes : undefined}
            lazyLoading={check.id === 'data_table_sizes' ? tableSizeLoading : false}
          />
        ))}
      </div>

      {tableSizes && (
        <div className="overflow-auto rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{__('Table', 'wp-sms')}</TableHead>
                <TableHead className="text-end">{__('Rows', 'wp-sms')}</TableHead>
                <TableHead className="text-end">{__('Data (MB)', 'wp-sms')}</TableHead>
                <TableHead className="text-end">{__('Index (MB)', 'wp-sms')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {tableSizes.map(entry => (
                <TableRow key={entry.table}>
                  <TableCell className="font-mono text-xs">{entry.table}</TableCell>
                  <TableCell className="text-end tabular-nums">{fmtNumber(entry.rows)}</TableCell>
                  <TableCell className="text-end tabular-nums">{entry.data_size_mb.toFixed(2)}</TableCell>
                  <TableCell className="text-end tabular-nums">{entry.index_size_mb.toFixed(2)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
