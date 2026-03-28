import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { useContacts } from '@/hooks/use-contacts';
import { useTags } from '@/hooks/use-tags';
import { useLists } from '@/hooks/use-lists';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/layout/page-header';
import { ContactsList } from '@/components/contacts/contacts-list';
import { ImportWizard } from '@/components/contacts/import-wizard';
import { ExportDialog } from '@/components/contacts/export-dialog';
import { TagsList } from '@/components/tags/tags-list';
import { ListsList } from '@/components/lists/lists-list';
import { SubscriptionForms } from './subscription-forms';
import { useSubscriptionForms } from '@/hooks/use-subscription-forms';
import { useSubTabs } from '@/hooks/use-sub-tabs';
import { Badge } from '@/components/ui/badge';
import { Users, Plus, Upload, Download } from 'lucide-react';

const TABS = ['contacts', 'tags', 'lists', 'forms'] as const;

interface ContactsProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function Contacts({ subTab, onNavigate }: ContactsProps) {
  const contactsHook = useContacts();
  const tagsHook = useTags();
  const listsHook = useLists();
  const formsHook = useSubscriptionForms();
  const [importOpen, setImportOpen] = useState(false);
  const [contactCreate, setContactCreate] = useState(0);
  const [tagCreate, setTagCreate] = useState(0);
  const [listCreate, setListCreate] = useState(0);
  const [formCreate, setFormCreate] = useState(0);

  const [activeTab, handleTabChange] = useSubTabs('contacts', TABS, subTab, onNavigate);

  const headerActions: Record<string, React.ReactNode> = {
    contacts: (
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
    ),
    tags: (
      <Button size="sm" onClick={() => setTagCreate((n) => n + 1)}>
        <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New Tag', 'wp-sms')}
      </Button>
    ),
    lists: (
      <Button size="sm" onClick={() => setListCreate((n) => n + 1)}>
        <Plus className="me-1.5 h-3.5 w-3.5" /> {__('New List', 'wp-sms')}
      </Button>
    ),
    forms: (
      <Button size="sm" onClick={() => setFormCreate((n) => n + 1)}>
        <Plus className="me-1.5 h-3.5 w-3.5" /> {__('Create Form', 'wp-sms')}
      </Button>
    ),
  };

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader
        icon={Users}
        title={__('Contacts', 'wp-sms')}
        actions={headerActions[activeTab]}
      >
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="contacts">{__('Contacts', 'wp-sms')} <TabCount count={contactsHook.total} /></TabsTrigger>
          <TabsTrigger value="tags">{__('Tags', 'wp-sms')} <TabCount count={tagsHook.tags.length} /></TabsTrigger>
          <TabsTrigger value="lists">{__('Lists', 'wp-sms')} <TabCount count={listsHook.lists.length} /></TabsTrigger>
          <TabsTrigger value="forms">{__('Forms', 'wp-sms')} <TabCount count={formsHook.forms.length} /></TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="contacts">
        <ContactsList
          hook={contactsHook}
          tags={tagsHook.tags}
          onImport={() => setImportOpen(true)}
          embedded
          createTrigger={contactCreate}
        />
      </TabsContent>

      <TabsContent value="tags">
        <TagsList hook={tagsHook} embedded createTrigger={tagCreate} />
      </TabsContent>

      <TabsContent value="lists">
        <ListsList hook={listsHook} tags={tagsHook.tags} embedded createTrigger={listCreate} />
      </TabsContent>

      <TabsContent value="forms">
        {activeTab === 'forms' && (
          <SubscriptionForms
            embedded
            hook={formsHook}
            createTrigger={formCreate}
          />
        )}
      </TabsContent>

      <ImportWizard
        open={importOpen}
        onOpenChange={setImportOpen}
        onPreview={contactsHook.importPreview}
        onImport={contactsHook.importContacts}
      />
    </Tabs>
  );
}

function TabCount({ count }: { count: number }) {
  if (count <= 0) return null;
  return (
    <Badge variant="secondary" className="ms-1.5 h-5 min-w-5 px-1 text-[10px]">
      {count}
    </Badge>
  );
}
