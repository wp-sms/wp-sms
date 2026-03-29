import { __ } from '@wordpress/i18n';
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
  { id: 'name' as const, label: __('Name', 'wp-sms') },
  { id: 'email' as const, label: __('Email', 'wp-sms') },
  { id: 'phone' as const, label: __('Phone', 'wp-sms') },
  { id: 'message' as const, label: __('Message', 'wp-sms') },
];

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
            {__('Welcome Page', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('The first page visitors see when opening the widget', 'wp-sms')}</CardDescription>
          <CardAction>
            <Switch
              checked={pages.welcome.enabled}
              onCheckedChange={(checked) => onUpdate('pages.welcome.enabled', checked)}
              aria-label={__('Toggle welcome page', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
        {pages.welcome.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel htmlFor="mb-greeting">{__('Greeting Message', 'wp-sms')}</FieldLabel>
                <Textarea
                  id="mb-greeting"
                  value={pages.welcome.greeting}
                  onChange={(e) => onUpdate('pages.welcome.greeting', e.target.value)}
                  placeholder={__('Welcome! Choose an option below.', 'wp-sms')}
                  rows={2}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="mb-cta-label">{__('CTA Button Label', 'wp-sms')}</FieldLabel>
                <Input
                  id="mb-cta-label"
                  value={pages.welcome.cta_label}
                  onChange={(e) => onUpdate('pages.welcome.cta_label', e.target.value)}
                  placeholder={__('Send a message', 'wp-sms')}
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
            {__('Contact Form', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Capture messages and contact information from visitors', 'wp-sms')}</CardDescription>
          <CardAction>
            <Switch
              checked={pages.contact_form.enabled}
              onCheckedChange={(checked) => onUpdate('pages.contact_form.enabled', checked)}
              aria-label={__('Toggle contact form', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
        {pages.contact_form.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel>{__('Form Fields', 'wp-sms')}</FieldLabel>
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
                          <span className="text-xs text-muted-foreground">{__('(required)', 'wp-sms')}</span>
                        )}
                      </div>
                    );
                  })}
                </div>
                <FieldDescription>{__('Choose which fields to show in the contact form', 'wp-sms')}</FieldDescription>
              </Field>

              <Field>
                <FieldLabel>{__('Notification Channel', 'wp-sms')}</FieldLabel>
                <Select
                  value={pages.contact_form.channel}
                  onValueChange={(v) => onUpdate('pages.contact_form.channel', v)}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="email">{__('Email', 'wp-sms')}</SelectItem>
                    <SelectItem value="sms">{__('SMS', 'wp-sms')}</SelectItem>
                  </SelectContent>
                </Select>
                <FieldDescription>{__('How notification of new messages is delivered to you', 'wp-sms')}</FieldDescription>
              </Field>

              <Field>
                <FieldLabel htmlFor="mb-recipients">{__('Notification Recipients', 'wp-sms')}</FieldLabel>
                <Input
                  id="mb-recipients"
                  value={pages.contact_form.notification_recipients.join(', ')}
                  onChange={(e) => {
                    const recipients = e.target.value.split(',').map((s) => s.trim()).filter(Boolean);
                    onUpdate('pages.contact_form.notification_recipients', recipients);
                  }}
                  placeholder={__('admin@example.com, support@example.com', 'wp-sms')}
                />
                <FieldDescription>{__('Comma-separated list. Defaults to site admin email if empty.', 'wp-sms')}</FieldDescription>
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
            {__('Resources & Links', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Add helpful links and resources to the widget', 'wp-sms')}</CardDescription>
          <CardAction>
            <Switch
              checked={pages.resources.enabled}
              onCheckedChange={(checked) => onUpdate('pages.resources.enabled', checked)}
              aria-label={__('Toggle resources', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
        {pages.resources.enabled && (
          <CardContent>
            <div className="space-y-3">
              {pages.resources.links.length === 0 && (
                <EmptyState
                  icon={Link}
                  title={__('No links yet', 'wp-sms')}
                  description={__('Add helpful links and resources to display in the widget.', 'wp-sms')}
                  action={<Button onClick={addResourceLink}><Plus className="me-1 h-4 w-4" /> {__('Add Link', 'wp-sms')}</Button>}
                  compact
                />
              )}
              {pages.resources.links.map((link, i) => (
                <div key={i} className="space-y-2 rounded-md border p-3">
                  <div className="flex gap-2">
                    <Input
                      value={link.title}
                      onChange={(e) => updateResourceLink(i, 'title', e.target.value)}
                      placeholder={__('Title', 'wp-sms')}
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
                    placeholder={__('Brief description (optional)', 'wp-sms')}
                  />
                </div>
              ))}
              {pages.resources.links.length > 0 && (
                <Button variant="outline" size="sm" onClick={addResourceLink}>
                  <Plus className="me-1 h-4 w-4" /> {__('Add Link', 'wp-sms')}
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
            {__('GDPR Consent', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Require privacy consent before form submission', 'wp-sms')}</CardDescription>
          <CardAction>
            <Switch
              checked={gdpr.enabled}
              onCheckedChange={(checked) => onUpdate('gdpr.enabled', checked)}
              aria-label={__('Toggle GDPR consent', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
        {gdpr.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel htmlFor="mb-consent-text">{__('Consent Text', 'wp-sms')}</FieldLabel>
                <Input
                  id="mb-consent-text"
                  value={gdpr.consent_text}
                  onChange={(e) => onUpdate('gdpr.consent_text', e.target.value)}
                  placeholder={__('I agree to the privacy policy.', 'wp-sms')}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="mb-privacy-url">{__('Privacy Policy URL', 'wp-sms')}</FieldLabel>
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
