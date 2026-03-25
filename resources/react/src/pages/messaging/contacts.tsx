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
import { Badge } from '@/components/ui/badge';
import { Users, Plus, Upload, Download } from 'lucide-react';

const VALID_TABS = ['contacts', 'tags', 'lists', 'sources'];

interface ContactsProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function Contacts({ subTab, onNavigate }: ContactsProps) {
  const contactsHook = useContacts();
  const tagsHook = useTags();
  const listsHook = useLists();
  const [importOpen, setImportOpen] = useState(false);
  const [contactCreate, setContactCreate] = useState(0);
  const [tagCreate, setTagCreate] = useState(0);
  const [listCreate, setListCreate] = useState(0);

  const activeTab = subTab && VALID_TABS.includes(subTab) ? subTab : 'contacts';

  const handleTabChange = (tab: string) => {
    onNavigate?.(tab === 'contacts' ? 'contacts' : `contacts/${tab}`);
  };

  const headerActions: Record<string, React.ReactNode> = {
    contacts: (
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
    tags: (
      <Button size="sm" onClick={() => setTagCreate((n) => n + 1)}>
        <Plus className="mr-1.5 h-3.5 w-3.5" /> New Tag
      </Button>
    ),
    lists: (
      <Button size="sm" onClick={() => setListCreate((n) => n + 1)}>
        <Plus className="mr-1.5 h-3.5 w-3.5" /> New List
      </Button>
    ),
  };

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader
        icon={Users}
        title="Contacts"
        actions={headerActions[activeTab]}
      >
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="contacts">Contacts <TabCount count={contactsHook.total} /></TabsTrigger>
          <TabsTrigger value="tags">Tags <TabCount count={tagsHook.tags.length} /></TabsTrigger>
          <TabsTrigger value="lists">Lists <TabCount count={listsHook.lists.length} /></TabsTrigger>
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
        <ListsList hook={listsHook} tags={tagsHook.tags} embedded createTrigger={listCreate} />
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

function TabCount({ count }: { count: number }) {
  if (count <= 0) return null;
  return (
    <Badge variant="secondary" className="ml-1.5 h-5 min-w-5 px-1 text-[10px]">
      {count}
    </Badge>
  );
}
