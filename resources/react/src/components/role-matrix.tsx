import { __, sprintf } from '@wordpress/i18n';
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
        {__('No roles available. Roles are loaded from WordPress.', 'wp-sms')}
      </p>
    );
  }

  return (
    <div className="rounded-lg border border-border/50 overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{__('Role', 'wp-sms')}</TableHead>
            <TableHead className="w-24 text-center">{__('MFA Required', 'wp-sms')}</TableHead>
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
                  aria-label={sprintf(__('Require MFA for %s', 'wp-sms'), name)}
                />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
