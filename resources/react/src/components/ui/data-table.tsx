import { Skeleton } from '@/components/ui/skeleton';
import { PageNumbers } from '@/components/ui/pagination';

interface DataTableProps extends React.ComponentProps<'div'> {
  loading?: boolean;
  skeletonRows?: number;
  isEmpty?: boolean;
  empty?: React.ReactNode;
  pagination?: {
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
  };
  children: React.ReactNode;
}

export function DataTable({
  loading,
  skeletonRows = 5,
  isEmpty,
  empty,
  pagination,
  children,
  className,
  ...props
}: DataTableProps) {
  if (loading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: skeletonRows }).map((_, i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (isEmpty) {
    return (
      <div className="rounded-lg border border-dashed border-border/50">
        {empty}
      </div>
    );
  }

  return (
    <div className={className} {...props}>
      <div className="border-2 border-border rounded-lg overflow-hidden">
        {children}
        {pagination && (
          <PageNumbers
            page={pagination.page}
            totalPages={pagination.totalPages}
            onPageChange={pagination.onPageChange}
          />
        )}
      </div>
    </div>
  );
}
