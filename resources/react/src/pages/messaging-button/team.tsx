import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { Users, Plus, Trash2, ChevronUp, ChevronDown, Upload, MessageSquareText } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
import type { MessagingButtonSettings } from './use-mb-settings';

interface TeamPageProps {
  settings: MessagingButtonSettings;
  onUpdate: (key: string, value: unknown) => void;
}

const CHANNEL_TYPES = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'sms', label: 'SMS' },
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
            Team Members
          </CardTitle>
          <CardDescription>
            Add team members to display in the widget with their contact methods
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {members.length === 0 && (
              <p className="text-sm text-muted-foreground text-center py-4">
                Add team members to display in the widget with their contact channels.
              </p>
            )}
            {members.map((member, i) => (
              <div key={i} className="rounded-md border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      onClick={() => moveMember(i, -1)}
                      disabled={i === 0}
                    >
                      <ChevronUp className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      onClick={() => moveMember(i, 1)}
                      disabled={i === members.length - 1}
                    >
                      <ChevronDown className="h-4 w-4" />
                    </Button>
                    <span className="ml-1 text-sm font-medium text-muted-foreground">
                      Member {i + 1}
                    </span>
                  </div>
                  <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => removeMember(i)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <Field>
                    <FieldLabel>Name</FieldLabel>
                    <Input
                      value={member.name}
                      onChange={(e) => updateMember(i, 'name', e.target.value)}
                      placeholder="John Doe"
                    />
                  </Field>
                  <Field>
                    <FieldLabel>Role</FieldLabel>
                    <Input
                      value={member.role}
                      onChange={(e) => updateMember(i, 'role', e.target.value)}
                      placeholder="Support Agent"
                    />
                  </Field>
                </div>

                <Field>
                  <FieldLabel>Avatar</FieldLabel>
                  <div className="flex items-center gap-3">
                    {member.avatar_url && (
                      <img
                        src={member.avatar_url}
                        alt=""
                        className="h-10 w-10 rounded-full object-cover border"
                      />
                    )}
                    <div className="flex gap-2 flex-1">
                      <Input
                        value={member.avatar_url}
                        onChange={(e) => updateMember(i, 'avatar_url', e.target.value)}
                        placeholder="Image URL or upload"
                        className="flex-1"
                      />
                      <Button
                        variant="outline"
                        size="icon"
                        type="button"
                        onClick={() => openMediaLibrary('Select Avatar', (url) => updateMember(i, 'avatar_url', url))}
                        title="Upload from Media Library"
                      >
                        <Upload className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </Field>

                <div className="space-y-2">
                  <span className="text-sm font-medium">Contact Methods</span>
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
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  <Button variant="outline" size="sm" onClick={() => addContactMethod(i)}>
                    <Plus className="mr-1 h-3 w-3" /> Add Method
                  </Button>
                </div>
              </div>
            ))}

            <Button variant="outline" onClick={addMember}>
              <Plus className="mr-1 h-4 w-4" /> Add Team Member
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <MessageSquareText className="h-4 w-4 text-muted-foreground" />
            Pre-filled Message
          </CardTitle>
          <CardDescription>
            Pre-fill a message when visitors click contact links
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Field>
            <FieldLabel htmlFor="mb-default-message">Default Message</FieldLabel>
            <Input
              id="mb-default-message"
              value={settings.default_message}
              onChange={(e) => onUpdate('default_message', e.target.value)}
              placeholder="Hi! I'm visiting {page_title} and have a question."
            />
            <FieldDescription>
              Placeholders: {'{page_title}'}, {'{page_url}'}, {'{member_name}'}. Leave empty to disable.
            </FieldDescription>
          </Field>
        </CardContent>
      </Card>
    </div>
  );
}
