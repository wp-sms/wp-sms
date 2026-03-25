import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Home, MessageSquare, Link, Plus, Trash2, Shield } from 'lucide-react';
import type { MessagingButtonSettings } from './use-mb-settings';

interface PagesConfigPageProps {
  settings: MessagingButtonSettings;
  onUpdate: (key: string, value: unknown) => void;
}

const FORM_FIELDS = [
  { id: 'name', label: 'Name' },
  { id: 'email', label: 'Email' },
  { id: 'phone', label: 'Phone' },
  { id: 'message', label: 'Message' },
] as const;

export function PagesConfigPage({ settings, onUpdate }: PagesConfigPageProps) {
  const { pages, gdpr } = settings;

  const toggleField = (field: string, checked: boolean) => {
    if (pages.contact_form.required_fields.includes(field)) return;
    const fields = checked
      ? [...pages.contact_form.fields, field]
      : pages.contact_form.fields.filter((f) => f !== field);
    onUpdate('pages.contact_form.fields', fields);
  };

  const addResourceLink = () => {
    const links = [...(pages.resources.links || []), { title: '', url: '', description: '' }];
    onUpdate('pages.resources.links', links);
  };

  const removeResourceLink = (index: number) => {
    const links = pages.resources.links.filter((_, i) => i !== index);
    onUpdate('pages.resources.links', links);
  };

  const updateResourceLink = (index: number, field: string, value: string) => {
    const links = pages.resources.links.map((link, i) =>
      i === index ? { ...link, [field]: value } : link
    );
    onUpdate('pages.resources.links', links);
  };

  return (
    <div className="space-y-4">
      {/* Welcome Page */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Home className="h-4 w-4 text-muted-foreground" />
            Welcome Page
          </CardTitle>
          <CardDescription>The first page visitors see when opening the widget</CardDescription>
          <CardAction>
            <Switch
              checked={pages.welcome.enabled}
              onCheckedChange={(checked) => onUpdate('pages.welcome.enabled', checked)}
              aria-label="Toggle welcome page"
            />
          </CardAction>
        </CardHeader>
        {pages.welcome.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel htmlFor="mb-greeting">Greeting Message</FieldLabel>
                <Textarea
                  id="mb-greeting"
                  value={pages.welcome.greeting}
                  onChange={(e) => onUpdate('pages.welcome.greeting', e.target.value)}
                  placeholder="Welcome! Choose an option below."
                  rows={2}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="mb-cta-label">CTA Button Label</FieldLabel>
                <Input
                  id="mb-cta-label"
                  value={pages.welcome.cta_label}
                  onChange={(e) => onUpdate('pages.welcome.cta_label', e.target.value)}
                  placeholder="Send a message"
                />
              </Field>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Contact Form */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <MessageSquare className="h-4 w-4 text-muted-foreground" />
            Contact Form
          </CardTitle>
          <CardDescription>Capture messages and contact information from visitors</CardDescription>
          <CardAction>
            <Switch
              checked={pages.contact_form.enabled}
              onCheckedChange={(checked) => onUpdate('pages.contact_form.enabled', checked)}
              aria-label="Toggle contact form"
            />
          </CardAction>
        </CardHeader>
        {pages.contact_form.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel>Form Fields</FieldLabel>
                <div className="space-y-3">
                  {FORM_FIELDS.map(({ id, label }) => {
                    const isRequired = pages.contact_form.required_fields.includes(id);
                    return (
                      <div key={id} className="flex items-center gap-2">
                        <Checkbox
                          id={`field-${id}`}
                          checked={pages.contact_form.fields.includes(id)}
                          onCheckedChange={(checked) => toggleField(id, checked === true)}
                          disabled={isRequired}
                        />
                        <Label
                          htmlFor={`field-${id}`}
                          className={`text-sm font-normal ${isRequired ? 'text-muted-foreground' : 'cursor-pointer'}`}
                        >
                          {label}
                        </Label>
                        {isRequired && (
                          <span className="text-xs text-muted-foreground">(required)</span>
                        )}
                      </div>
                    );
                  })}
                </div>
                <FieldDescription>Choose which fields to show in the contact form</FieldDescription>
              </Field>

              <Field>
                <FieldLabel>Notification Channel</FieldLabel>
                <Select
                  value={pages.contact_form.channel}
                  onValueChange={(v) => onUpdate('pages.contact_form.channel', v)}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="email">Email</SelectItem>
                    <SelectItem value="sms">SMS</SelectItem>
                  </SelectContent>
                </Select>
                <FieldDescription>How notification of new messages is delivered to you</FieldDescription>
              </Field>

              <Field>
                <FieldLabel htmlFor="mb-recipients">Notification Recipients</FieldLabel>
                <Input
                  id="mb-recipients"
                  value={pages.contact_form.notification_recipients.join(', ')}
                  onChange={(e) => {
                    const recipients = e.target.value.split(',').map((s) => s.trim()).filter(Boolean);
                    onUpdate('pages.contact_form.notification_recipients', recipients);
                  }}
                  placeholder="admin@example.com, support@example.com"
                />
                <FieldDescription>Comma-separated list. Defaults to site admin email if empty.</FieldDescription>
              </Field>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Resources */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Link className="h-4 w-4 text-muted-foreground" />
            Resources & Links
          </CardTitle>
          <CardDescription>Add helpful links and resources to the widget</CardDescription>
          <CardAction>
            <Switch
              checked={pages.resources.enabled}
              onCheckedChange={(checked) => onUpdate('pages.resources.enabled', checked)}
              aria-label="Toggle resources"
            />
          </CardAction>
        </CardHeader>
        {pages.resources.enabled && (
          <CardContent>
            <div className="space-y-3">
              {pages.resources.links.length === 0 && (
                <EmptyState
                  icon={Link}
                  title="No links yet"
                  description="Add helpful links and resources to display in the widget."
                  action={<Button onClick={addResourceLink}><Plus className="mr-1 h-4 w-4" /> Add Link</Button>}
                  compact
                />
              )}
              {pages.resources.links.map((link, i) => (
                <div key={i} className="space-y-2 rounded-md border p-3">
                  <div className="flex gap-2">
                    <Input
                      value={link.title}
                      onChange={(e) => updateResourceLink(i, 'title', e.target.value)}
                      placeholder="Title"
                      className="w-1/3"
                    />
                    <Input
                      value={link.url}
                      onChange={(e) => updateResourceLink(i, 'url', e.target.value)}
                      placeholder="https://..."
                      className="flex-1"
                    />
                    <Button variant="ghost" size="icon" onClick={() => removeResourceLink(i)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                  <Input
                    value={link.description}
                    onChange={(e) => updateResourceLink(i, 'description', e.target.value)}
                    placeholder="Brief description (optional)"
                  />
                </div>
              ))}
              {pages.resources.links.length > 0 && (
                <Button variant="outline" size="sm" onClick={addResourceLink}>
                  <Plus className="mr-1 h-4 w-4" /> Add Link
                </Button>
              )}
            </div>
          </CardContent>
        )}
      </Card>

      {/* GDPR */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Shield className="h-4 w-4 text-muted-foreground" />
            GDPR Consent
          </CardTitle>
          <CardDescription>Require privacy consent before form submission</CardDescription>
          <CardAction>
            <Switch
              checked={gdpr.enabled}
              onCheckedChange={(checked) => onUpdate('gdpr.enabled', checked)}
              aria-label="Toggle GDPR consent"
            />
          </CardAction>
        </CardHeader>
        {gdpr.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel htmlFor="mb-consent-text">Consent Text</FieldLabel>
                <Input
                  id="mb-consent-text"
                  value={gdpr.consent_text}
                  onChange={(e) => onUpdate('gdpr.consent_text', e.target.value)}
                  placeholder="I agree to the privacy policy."
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="mb-privacy-url">Privacy Policy URL</FieldLabel>
                <Input
                  id="mb-privacy-url"
                  value={gdpr.link_url}
                  onChange={(e) => onUpdate('gdpr.link_url', e.target.value)}
                  placeholder="https://example.com/privacy"
                />
              </Field>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );
}
