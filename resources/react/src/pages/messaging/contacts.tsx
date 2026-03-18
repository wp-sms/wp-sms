import { useState } from 'react';
import { useContacts } from '@/hooks/use-contacts';
import { useTags } from '@/hooks/use-tags';
import { useLists } from '@/hooks/use-lists';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ContactsList } from '@/components/contacts/contacts-list';
import { ImportWizard } from '@/components/contacts/import-wizard';
import { TagsList } from '@/components/tags/tags-list';
import { ListsList } from '@/components/lists/lists-list';

const VALID_TABS = ['contacts', 'tags', 'lists'];

interface ContactsProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function Contacts({ subTab, onNavigate }: ContactsProps) {
  const contactsHook = useContacts();
  const tagsHook = useTags();
  const [importOpen, setImportOpen] = useState(false);

  const activeTab = subTab && VALID_TABS.includes(subTab) ? subTab : 'contacts';

  const handleTabChange = (tab: string) => {
    onNavigate?.(tab === 'contacts' ? 'contacts' : `contacts/${tab}`);
  };

  return (
    <div className="space-y-4">
      <Tabs value={activeTab} onValueChange={handleTabChange}>
        <TabsList>
          <TabsTrigger value="contacts">Contacts</TabsTrigger>
          <TabsTrigger value="tags">Tags</TabsTrigger>
          <TabsTrigger value="lists">Lists</TabsTrigger>
        </TabsList>

        <TabsContent value="contacts">
          <ContactsList
            hook={contactsHook}
            tags={tagsHook.tags}
            onImport={() => setImportOpen(true)}
          />
        </TabsContent>

        <TabsContent value="tags">
          <TagsList hook={tagsHook} />
        </TabsContent>

        <TabsContent value="lists">
          {activeTab === 'lists' && <LazyListsList tags={tagsHook.tags} />}
        </TabsContent>
      </Tabs>

      <ImportWizard
        open={importOpen}
        onOpenChange={setImportOpen}
        onPreview={contactsHook.importPreview}
        onImport={contactsHook.importContacts}
      />
    </div>
  );
}

function LazyListsList({ tags }: { tags: ReturnType<typeof useTags>['tags'] }) {
  const listsHook = useLists();
  return <ListsList hook={listsHook} tags={tags} />;
}
