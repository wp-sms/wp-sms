import { __, sprintf } from '@wordpress/i18n';
import { onActivate } from '@/lib/utils';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Users, Plus, Trash2, ChevronUp, ChevronDown, Upload, X, MessageSquareText } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
import type { MessagingButtonSettings } from './use-mb-settings';

interface TeamPageProps {
  settings: MessagingButtonSettings;
  onUpdate: (key: string, value: unknown) => void;
}

const CHANNEL_TYPES = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'email', label: __('Email', 'wp-sms') },
  { value: 'phone', label: __('Phone', 'wp-sms') },
  { value: 'sms', label: __('SMS', 'wp-sms') },
];

export function TeamPage({ settings, onUpdate }: TeamPageProps) {
  const members = settings.team_members;

  const setMembers = (updated: typeof members) => onUpdate('team_members', updated);

  const addMember = () => {
    setMembers([
      ...members,
      { name: '', role: '', avatar_url: '', contact_methods: [{ type: 'email', value: '' }] },
    ]);
  };

  const removeMember = (index: number) => {
    setMembers(members.filter((_, i) => i !== index));
  };

  const updateMember = (index: number, field: string, value: unknown) => {
    setMembers(members.map((m, i) => (i === index ? { ...m, [field]: value } : m)));
  };

  const moveMember = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= members.length) return;
    const updated = [...members];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    setMembers(updated);
  };

  const addContactMethod = (memberIndex: number) => {
    const member = members[memberIndex];
    updateMember(memberIndex, 'contact_methods', [
      ...member.contact_methods,
      { type: 'email', value: '' },
    ]);
  };

  const removeContactMethod = (memberIndex: number, methodIndex: number) => {
    const member = members[memberIndex];
    updateMember(
      memberIndex,
      'contact_methods',
      member.contact_methods.filter((_, i) => i !== methodIndex),
    );
  };

  const updateContactMethod = (memberIndex: number, methodIndex: number, field: string, value: string) => {
    const member = members[memberIndex];
    const methods = member.contact_methods.map((m, i) =>
      i === methodIndex ? { ...m, [field]: value } : m
    );
    updateMember(memberIndex, 'contact_methods', methods);
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Users className="h-4 w-4 text-muted-foreground" />
            {__('Team Members', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Add team members to display in the widget with their contact methods', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {members.length === 0 && (
              <EmptyState
                icon={Users}
                title={__('No team members yet', 'wp-sms')}
                description={__('Add team members to display in the widget with their contact channels.', 'wp-sms')}
                action={<Button onClick={addMember}><Plus className="me-1 h-4 w-4" /> {__('Add Team Member', 'wp-sms')}</Button>}
                compact
              />
            )}
            {members.map((member, i) => (
              <div key={i} className="rounded-md border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="icon-md"
                      onClick={() => moveMember(i, -1)}
                      disabled={i === 0}
                    >
                      <ChevronUp className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon-md"
                      onClick={() => moveMember(i, 1)}
                      disabled={i === members.length - 1}
                    >
                      <ChevronDown className="h-4 w-4" />
                    </Button>
                    <span className="ms-1 text-sm font-medium text-muted-foreground">
                      {sprintf(__('Member %d', 'wp-sms'), i + 1)}
                    </span>
                  </div>
                  <Button variant="ghost" size="icon-md" onClick={() => removeMember(i)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <Field>
                    <FieldLabel>{__('Name', 'wp-sms')}</FieldLabel>
                    <Input
                      value={member.name}
                      onChange={(e) => updateMember(i, 'name', e.target.value)}
                      placeholder={__('John Doe', 'wp-sms')}
                    />
                  </Field>
                  <Field>
                    <FieldLabel>{__('Role', 'wp-sms')}</FieldLabel>
                    <Input
                      value={member.role}
                      onChange={(e) => updateMember(i, 'role', e.target.value)}
                      placeholder={__('Support Agent', 'wp-sms')}
                    />
                  </Field>
                </div>

                <Field>
                  <FieldLabel>{__('Avatar', 'wp-sms')}</FieldLabel>
                  <div className="flex items-center gap-3">
                    {member.avatar_url ? (
                      <div
                        role="button"
                        tabIndex={0}
                        aria-label={__('Change avatar', 'wp-sms')}
                        className="group relative h-12 w-12 shrink-0 cursor-pointer overflow-hidden rounded-full border"
                        onClick={() => openMediaLibrary('Select Avatar', (url) => updateMember(i, 'avatar_url', url))}
                        onKeyDown={onActivate(() => openMediaLibrary('Select Avatar', (url) => updateMember(i, 'avatar_url', url)))}
                      >
                        <img
                          src={member.avatar_url}
                          alt=""
                          className="h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                          <Upload className="h-3.5 w-3.5 text-white" />
                        </div>
                      </div>
                    ) : (
                      <div
                        role="button"
                        tabIndex={0}
                        aria-label={__('Upload avatar', 'wp-sms')}
                        className="flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-input transition-colors hover:border-primary/30 hover:bg-primary/5"
                        onClick={() => openMediaLibrary('Select Avatar', (url) => updateMember(i, 'avatar_url', url))}
                        onKeyDown={onActivate(() => openMediaLibrary('Select Avatar', (url) => updateMember(i, 'avatar_url', url)))}
                      >
                        <Upload className="h-4 w-4 text-muted-foreground/50" />
                      </div>
                    )}
                    {member.avatar_url && (
                      <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                        onClick={() => updateMember(i, 'avatar_url', '')}
                      >
                        <X className="me-1 h-3 w-3" /> {__('Remove', 'wp-sms')}
                      </Button>
                    )}
                  </div>
                </Field>

                <div className="space-y-2">
                  <span className="text-sm font-medium">{__('Contact Methods', 'wp-sms')}</span>
                  {member.contact_methods.map((method, j) => (
                    <div key={j} className="flex gap-2">
                      <Select
                        value={method.type}
                        onValueChange={(v) => updateContactMethod(i, j, 'type', v)}
                      >
                        <SelectTrigger className="w-32">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {CHANNEL_TYPES.map((ch) => (
                            <SelectItem key={ch.value} value={ch.value}>{ch.label}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Input
                        dir="ltr"
                        value={method.value}
                        onChange={(e) => updateContactMethod(i, j, 'value', e.target.value)}
                        placeholder={method.type === 'email' ? 'email@example.com' : '+1234567890'}
                        className="flex-1"
                      />
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => removeContactMethod(i, j)}
                        disabled={member.contact_methods.length <= 1}
                        aria-label={__('Remove contact method', 'wp-sms')}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  <Button variant="outline" size="sm" onClick={() => addContactMethod(i)}>
                    <Plus className="me-1 h-3 w-3" /> {__('Add Method', 'wp-sms')}
                  </Button>
                </div>
              </div>
            ))}

            <Button variant="outline" onClick={addMember}>
              <Plus className="me-1 h-4 w-4" /> {__('Add Team Member', 'wp-sms')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <MessageSquareText className="h-4 w-4 text-muted-foreground" />
            {__('Pre-filled Message', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Pre-fill a message when visitors click contact links', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Field>
            <FieldLabel htmlFor="mb-default-message">{__('Default Message', 'wp-sms')}</FieldLabel>
            <Input
              id="mb-default-message"
              value={settings.default_message}
              onChange={(e) => onUpdate('default_message', e.target.value)}
              placeholder={__('Hi! I\'m visiting {page_title} and have a question.', 'wp-sms')}
            />
            <FieldDescription>
              {sprintf(__('Placeholders: %1$s, %2$s, %3$s. Leave empty to disable.', 'wp-sms'), '{page_title}', '{page_url}', '{member_name}')}
            </FieldDescription>
          </Field>
        </CardContent>
      </Card>
    </div>
  );
}
