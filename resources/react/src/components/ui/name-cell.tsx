import type { ReactNode } from 'react';
import { TableCell } from '@/components/ui/table';

interface NameCellProps {
  onClick: () => void;
  children: ReactNode;
}

export function NameCell({ onClick, children }: NameCellProps) {
  return (
    <TableCell className="font-medium">
      <button
        type="button"
        className="w-full text-start outline-none transition-colors hover:text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 rounded-sm"
        onClick={onClick}
      >
        {children}
      </button>
    </TableCell>
  );
}
