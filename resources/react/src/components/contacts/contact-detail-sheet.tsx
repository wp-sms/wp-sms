import { useState, useEffect, useCallback } from 'react';
import type { ContactDetail, Tag } from '@/lib/api';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { ContactTagsManager } from './contact-tags-manager';
import { ContactCustomFields } from './contact-custom-fields';
import { ContactWpUserInfo } from './contact-wp-user-info';
import { ContactActivity } from './contact-activity';
import { formatLabel } from '@/lib/constants';
import { CheckCircle2, XCircle } from 'lucide-react';

interface ContactDetailSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contactId: string | null;
  fetchContact: (id: string) => Promise<ContactDetail>;
  allTags: Tag[];
  onAddTag: (contactId: string, tagId: string) => Promise<void>;
  onRemoveTag: (contactId: string, tagId: string) => Promise<void>;
}

function VerifiedBadge({ verified }: { verified: boolean }) {
  if (verified) {
    return (
      <span className="inline-flex items-center gap-1 text-xs text-emerald-600">
        <CheckCircle2 className="h-3.5 w-3.5" />
        Verified
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
      <XCircle className="h-3.5 w-3.5" />
      Not verified
    </span>
  );
}

export function ContactDetailSheet({
  open, onOpenChange, contactId, fetchContact, allTags, onAddTag, onRemoveTag,
}: ContactDetailSheetProps) {
  const [contact, setContact] = useState<ContactDetail | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!contactId || !open) {
      setContact(null);
      return;
    }

    setLoading(true);
    fetchContact(contactId)
      .then(setContact)
      .catch(() => setContact(null))
      .finally(() => setLoading(false));
  }, [contactId, open, fetchContact]);

  const refreshContact = useCallback(async () => {
    if (contactId) {
      const updated = await fetchContact(contactId);
      setContact(updated);
    }
  }, [contactId, fetchContact]);

  const withRefresh = useCallback(
    (fn: (cId: string, tagId: string) => Promise<void>) =>
      async (cId: string, tagId: string) => {
        await fn(cId, tagId);
        await refreshContact();
      },
    [refreshContact],
  );

  const handleAddTag = withRefresh(onAddTag);
  const handleRemoveTag = withRefresh(onRemoveTag);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Contact Details</SheetTitle>
        </SheetHeader>

        {loading ? (
          <div className="space-y-4 px-4">
            <Skeleton className="h-8 w-48" />
            <Skeleton className="h-4 w-64" />
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-20 w-full" />
          </div>
        ) : contact ? (
          <div className="space-y-5 px-4 pb-4">
            {/* Contact info */}
            <div>
              <h3 className="text-lg font-semibold">
                {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || 'Unnamed'}
              </h3>
              <div className="mt-2 space-y-1.5 text-sm">
                {contact.email && (
                  <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">{contact.email}</span>
                    <VerifiedBadge verified={!!contact.email_verified} />
                  </div>
                )}
                {contact.phone && (
                  <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">{contact.phone}</span>
                    <VerifiedBadge verified={!!contact.phone_verified} />
                  </div>
                )}
              </div>
              <div className="mt-2 flex items-center gap-2">
                <Badge variant="outline">{formatLabel(contact.status)}</Badge>
                {contact.source && (
                  <span className="text-xs text-muted-foreground">Source: {contact.source}</span>
                )}
              </div>
            </div>

            {/* WP User */}
            {contact.wp_user && (
              <>
                <Separator />
                <ContactWpUserInfo wpUser={contact.wp_user} />
              </>
            )}

            {/* Tags */}
            <Separator />
            <div>
              <p className="text-sm font-medium mb-2">Tags</p>
              <ContactTagsManager
                contactId={contact.id}
                tags={contact.tags}
                allTags={allTags}
                onAdd={handleAddTag}
                onRemove={handleRemoveTag}
              />
            </div>

            {/* Custom fields */}
            {Object.keys(contact.custom_fields || {}).length > 0 && (
              <>
                <Separator />
                <div>
                  <p className="text-sm font-medium mb-2">Custom fields</p>
                  <ContactCustomFields fields={contact.custom_fields} readOnly />
                </div>
              </>
            )}

            {/* Activity */}
            <Separator />
            <div>
              <p className="text-sm font-medium mb-2">Activity</p>
              <ContactActivity contactId={contact.id} />
            </div>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground px-4">Contact not found</p>
        )}
      </SheetContent>
    </Sheet>
  );
}
