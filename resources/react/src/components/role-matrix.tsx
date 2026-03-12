import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Switch } from '@/components/ui/switch';

interface RoleMatrixProps {
  roles: Record<string, string>;
  selectedRoles: string[];
  onToggleRole: (roleKey: string, enabled: boolean) => void;
}

export function RoleMatrix({ roles, selectedRoles, onToggleRole }: RoleMatrixProps) {
  const roleEntries = Object.entries(roles);

  if (roleEntries.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No roles available. Roles are loaded from WordPress.
      </p>
    );
  }

  return (
    <div className="rounded-lg border border-border/50 overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Role</TableHead>
            <TableHead className="w-24 text-center">MFA Required</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {roleEntries.map(([key, name]) => (
            <TableRow key={key} className="even:bg-muted/30">
              <TableCell className="font-medium">{name}</TableCell>
              <TableCell className="text-center">
                <Switch
                  checked={selectedRoles.includes(key)}
                  onCheckedChange={(checked) => onToggleRole(key, checked)}
                  aria-label={`Require MFA for ${name}`}
                />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
