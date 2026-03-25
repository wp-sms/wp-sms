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
import { SourcesList } from '@/components/sources/sources-list';
import { pluralize } from '@/lib/utils';
import { Users, Plus, Upload, Download } from 'lucide-react';

const VALID_TABS = ['contacts', 'tags', 'lists', 'sources'];

interface ContactsProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function Contacts({ subTab, onNavigate }: ContactsProps) {
  const contactsHook = useContacts();
  const tagsHook = useTags();
  const [importOpen, setImportOpen] = useState(false);
  const [contactCreate, setContactCreate] = useState(0);
  const [tagCreate, setTagCreate] = useState(0);
  const [listCreate, setListCreate] = useState(0);

  const activeTab = subTab && VALID_TABS.includes(subTab) ? subTab : 'contacts';

  const handleTabChange = (tab: string) => {
    onNavigate?.(tab === 'contacts' ? 'contacts' : `contacts/${tab}`);
  };

  const headerProps: Record<string, { metadata?: React.ReactNode; actions?: React.ReactNode }> = {
    contacts: {
      metadata: pluralize(contactsHook.total, 'contact'),
      actions: (
        <>
          <Button variant="outline" size="sm" onClick={() => setImportOpen(true)}>
            <Upload className="mr-1.5 h-3.5 w-3.5" /> Import
          </Button>
          <ExportDialog onExport={contactsHook.exportContacts}>
            <Button variant="outline" size="sm">
              <Download className="mr-1.5 h-3.5 w-3.5" /> Export
            </Button>
          </ExportDialog>
          <Button size="sm" onClick={() => setContactCreate((n) => n + 1)}>
            <Plus className="mr-1.5 h-3.5 w-3.5" /> New Contact
          </Button>
        </>
      ),
    },
    tags: {
      metadata: pluralize(tagsHook.tags.length, 'tag'),
      actions: (
        <Button size="sm" onClick={() => setTagCreate((n) => n + 1)}>
          <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
        </Button>
      ),
    },
    lists: {
      actions: (
        <Button size="sm" onClick={() => setListCreate((n) => n + 1)}>
          <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
        </Button>
      ),
    },
    sources: {},
  };

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader
        icon={Users}
        title="Contacts"
        metadata={headerProps[activeTab]?.metadata}
        actions={headerProps[activeTab]?.actions}
      >
        <TabsList className="mt-3">
          <TabsTrigger value="contacts">Contacts</TabsTrigger>
          <TabsTrigger value="tags">Tags</TabsTrigger>
          <TabsTrigger value="lists">Lists</TabsTrigger>
          <TabsTrigger value="sources">Sources</TabsTrigger>
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
        {activeTab === 'lists' && <LazyListsList tags={tagsHook.tags} createTrigger={listCreate} />}
      </TabsContent>

      <TabsContent value="sources">
        {activeTab === 'sources' && <SourcesList />}
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

function LazyListsList({ tags, createTrigger }: { tags: ReturnType<typeof useTags>['tags']; createTrigger: number }) {
  const listsHook = useLists();
  return <ListsList hook={listsHook} tags={tags} embedded createTrigger={createTrigger} />;
}
