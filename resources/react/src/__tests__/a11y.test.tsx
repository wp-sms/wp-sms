import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { Field, FieldLabel, FieldDescription, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogAction,
  AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import {
  Select,
  SelectTrigger,
  SelectContent,
  SelectItem,
  SelectValue,
} from '@/components/ui/select';
import { DataTable } from '@/components/ui/data-table';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from '@/components/ui/table';
import { SaveBar } from '@/components/layout/save-bar';
import * as SaveBarContext from '@/contexts/save-bar-context';

describe('Accessibility (axe-core)', () => {
  it('Field + Input + FieldDescription + FieldError has no violations', async () => {
    const { container } = render(
      <Field>
        <FieldLabel htmlFor="email">{'Email'}</FieldLabel>
        <Input id="email" type="email" aria-describedby="email-desc email-err" />
        <FieldDescription id="email-desc">{'We will never share your email.'}</FieldDescription>
        <FieldError id="email-err">{'Email is required.'}</FieldError>
      </Field>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('Field + Input without errors has no violations', async () => {
    const { container } = render(
      <Field>
        <FieldLabel htmlFor="name">{'Full name'}</FieldLabel>
        <Input id="name" type="text" />
      </Field>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('Dialog with title and description has no violations', async () => {
    const { container } = render(
      <Dialog open>
        <DialogContent aria-describedby="dlg-desc">
          <DialogTitle>{'Settings'}</DialogTitle>
          <DialogDescription id="dlg-desc">{'Configure your preferences.'}</DialogDescription>
          <Input id="setting" type="text" aria-label="Setting value" />
        </DialogContent>
      </Dialog>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('AlertDialog has no violations', async () => {
    const { container } = render(
      <AlertDialog open>
        <AlertDialogContent>
          <AlertDialogTitle>{'Delete item?'}</AlertDialogTitle>
          <AlertDialogDescription>{'This action cannot be undone.'}</AlertDialogDescription>
          <AlertDialogCancel>{'Cancel'}</AlertDialogCancel>
          <AlertDialogAction>{'Delete'}</AlertDialogAction>
        </AlertDialogContent>
      </AlertDialog>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('Select with trigger and items has no violations', async () => {
    const { container } = render(
      <div>
        <label htmlFor="country-select">{'Country'}</label>
        <Select>
          <SelectTrigger id="country-select">
            <SelectValue placeholder="Select a country" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="us">{'United States'}</SelectItem>
            <SelectItem value="uk">{'United Kingdom'}</SelectItem>
          </SelectContent>
        </Select>
      </div>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('DataTable with content has no violations', async () => {
    const { container } = render(
      <DataTable>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{'Name'}</TableHead>
              <TableHead>{'Email'}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow>
              <TableCell>{'Alice'}</TableCell>
              <TableCell>{'alice@example.com'}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </DataTable>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('DataTable in loading state has no violations', async () => {
    const { container } = render(
      <DataTable loading skeletonRows={3}>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{'Name'}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow>
              <TableCell>{'placeholder'}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </DataTable>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('SaveBar with dirty state has no violations', async () => {
    vi.spyOn(SaveBarContext, 'useSaveBarState').mockReturnValue({
      isDirty: true,
      saveStatus: 'idle',
      onSave: () => {},
    });

    const { container } = render(<SaveBar />);
    const results = await axe(container);
    expect(results).toHaveNoViolations();

    vi.restoreAllMocks();
  });
});
