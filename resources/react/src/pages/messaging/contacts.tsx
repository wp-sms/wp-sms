import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { useContacts } from '@/hooks/use-contacts';
import { useTags } from '@/hooks/use-tags';
import { useLists } from '@/hooks/use-lists';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/layout/page-header';
import { ContactsList } from '@/components/contacts/contacts-list';
import { ImportWizard } from '@/components/contacts/import-wizard';
import { ExportDialog } from '@/components/contacts/export-dialog';
import { TagsList } from '@/components/tags/tags-list';
import { ListsList } from '@/components/lists/lists-list';
import { SubscriptionForms } from './subscription-forms';
import { useSubscriptionForms } from '@/hooks/use-subscription-forms';
import { Users, Plus, Upload, Download, Tag, List, FileText } from 'lucide-react';

interface ContactsProps {
  subTab?: string;
}

export function Contacts({ subTab }: ContactsProps) {
  const contactsHook = useContacts();
  const tagsHook = useTags();
  const listsHook = useLists();
  const formsHook = useSubscriptionForms();
  const [importOpen, setImportOpen] = useState(false);
  const [contactCreate, setContactCreate] = useState(0);
  const [tagCreate, setTagCreate] = useState(0);
  const [listCreate, setListCreate] = useState(0);
  const [formCreate, setFormCreate] = useState(0);

  switch (subTab) {
    case 'tags':
      return (
        <div className="space-y-4">
          <PageHeader
            icon={Tag}
            title={__('Tags', 'wp-sms')}
            actions={
              <Button size="sm" onClick={() => setTagCreate((n) => n + 1)}>
                <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Tag', 'wp-sms')}
              </Button>
            }
          />
          <TagsList hook={tagsHook} embedded createTrigger={tagCreate} />
        </div>
      );

    case 'lists':
      return (
        <div className="space-y-4">
          <PageHeader
            icon={List}
            title={__('Lists', 'wp-sms')}
            actions={
              <Button size="sm" onClick={() => setListCreate((n) => n + 1)}>
                <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New List', 'wp-sms')}
              </Button>
            }
          />
          <ListsList hook={listsHook} tags={tagsHook.tags} embedded createTrigger={listCreate} />
        </div>
      );

    case 'forms':
      return (
        <div className="space-y-4">
          <PageHeader
            icon={FileText}
            title={__('Subscription Forms', 'wp-sms')}
            actions={
              <Button size="sm" onClick={() => setFormCreate((n) => n + 1)}>
                <Plus className="me-1.5 h-3.5 w-3.5" /> {__('Create Form', 'wp-sms')}
              </Button>
            }
          />
          <SubscriptionForms
            embedded
            hook={formsHook}
            createTrigger={formCreate}
          />
        </div>
      );

    default:
      return (
        <div className="space-y-4">
          <PageHeader
            icon={Users}
            title={__('Contacts', 'wp-sms')}
            actions={
              <>
                <Button variant="outline" size="sm" onClick={() => setImportOpen(true)}>
                  <Upload className="me-1.5 h-3.5 w-3.5" /> {__('Import', 'wp-sms')}
                </Button>
                <ExportDialog onExport={contactsHook.exportContacts}>
                  <Button variant="outline" size="sm">
                    <Download className="me-1.5 h-3.5 w-3.5" /> {__('Export', 'wp-sms')}
                  </Button>
                </ExportDialog>
                <Button size="sm" onClick={() => setContactCreate((n) => n + 1)}>
                  <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Contact', 'wp-sms')}
                </Button>
              </>
            }
          />
          <ContactsList
            hook={contactsHook}
            tags={tagsHook.tags}
            onImport={() => setImportOpen(true)}
            embedded
            createTrigger={contactCreate}
          />
          <ImportWizard
            open={importOpen}
            onOpenChange={setImportOpen}
            onPreview={contactsHook.importPreview}
            onImport={contactsHook.importContacts}
          />
        </div>
      );
  }
}
