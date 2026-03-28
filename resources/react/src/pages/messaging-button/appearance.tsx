import { __ } from '@wordpress/i18n';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { ColorPickerField } from '@/components/ui/color-picker-field';
import { Paintbrush, PanelTop, Sun, Moon, Monitor, MessageCircle } from 'lucide-react';
import type { MessagingButtonSettings } from './use-mb-settings';

interface AppearancePageProps {
  settings: MessagingButtonSettings;
  onUpdate: (key: string, value: unknown) => void;
}

export function AppearancePage({ settings, onUpdate }: AppearancePageProps) {
  const { button, widget, greeting_bubble } = settings;
  const hasCustomAppearance = button.primary_color !== null;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Paintbrush className="h-4 w-4 text-muted-foreground" />
            {__('Button Appearance', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Customize the floating button style and position', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <Field>
                <FieldLabel>Position</FieldLabel>
                <Select value={button.position} onValueChange={(v) => onUpdate('button.position', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="bottom-right">{__('Bottom Right', 'wp-sms')}</SelectItem>
                    <SelectItem value="bottom-left">{__('Bottom Left', 'wp-sms')}</SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel>Style</FieldLabel>
                <Select value={button.style} onValueChange={(v) => onUpdate('button.style', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="icon-text">{__('Icon + Text', 'wp-sms')}</SelectItem>
                    <SelectItem value="icon">{__('Icon Only', 'wp-sms')}</SelectItem>
                    <SelectItem value="text">{__('Text Only', 'wp-sms')}</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </div>

            <Field>
              <FieldLabel htmlFor="mb-button-text">Button Text</FieldLabel>
              <Input
                id="mb-button-text"
                value={button.text}
                onChange={(e) => onUpdate('button.text', e.target.value)}
                placeholder={__('Chat with us', 'wp-sms')}
              />
              <FieldDescription>Displayed when style includes text</FieldDescription>
            </Field>

            <Field orientation="horizontal">
              <div className="flex-1">
                <FieldLabel>Custom appearance</FieldLabel>
                <FieldDescription>When off, inherits colors and theme from central branding.</FieldDescription>
              </div>
              <Switch
                checked={hasCustomAppearance}
                onCheckedChange={(checked) => {
                  if (checked) {
                    onUpdate('button.primary_color', '#2563eb');
                  } else {
                    onUpdate('button.primary_color', null);
                  }
                }}
              />
            </Field>

            {hasCustomAppearance && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <ColorPickerField
                    id="mb-primary-color"
                    label="Primary Color"
                    value={button.primary_color!}
                    placeholder="#2563eb"
                    onChange={(v) => onUpdate('button.primary_color', v)}
                  />
                  <ColorPickerField
                    id="mb-text-color"
                    label="Text Color"
                    value={button.text_color}
                    placeholder="#ffffff"
                    onChange={(v) => onUpdate('button.text_color', v)}
                  />
                </div>

                <Field>
                  <FieldLabel>Theme</FieldLabel>
                  <SegmentedGroup
                    value={widget.theme}
                    onChange={(v) => onUpdate('widget.theme', v)}
                    options={[
                      { value: 'light', label: 'Light', icon: <Sun className="h-4 w-4" /> },
                      { value: 'dark', label: 'Dark', icon: <Moon className="h-4 w-4" /> },
                      { value: 'system', label: 'System', icon: <Monitor className="h-4 w-4" /> },
                    ]}
                    size="labeled"
                  />
                </Field>
              </>
            )}

            <Field>
              <FieldLabel>Attention Effect</FieldLabel>
              <Select value={button.attention} onValueChange={(v) => onUpdate('button.attention', v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">{__('None', 'wp-sms')}</SelectItem>
                  <SelectItem value="pulse">{__('Pulse', 'wp-sms')}</SelectItem>
                  <SelectItem value="bounce">{__('Bounce', 'wp-sms')}</SelectItem>
                  <SelectItem value="badge">{__('Badge', 'wp-sms')}</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <PanelTop className="h-4 w-4 text-muted-foreground" />
            {__('Widget Header', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Set the greeting shown when the widget opens', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Field>
              <FieldLabel htmlFor="mb-widget-title">Title</FieldLabel>
              <Input
                id="mb-widget-title"
                value={widget.title}
                onChange={(e) => onUpdate('widget.title', e.target.value)}
                placeholder={__('Hi there!', 'wp-sms')}
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="mb-widget-subtitle">Subtitle</FieldLabel>
              <Input
                id="mb-widget-subtitle"
                value={widget.subtitle}
                onChange={(e) => onUpdate('widget.subtitle', e.target.value)}
                placeholder={__('How can we help?', 'wp-sms')}
              />
            </Field>
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <MessageCircle className="h-4 w-4 text-muted-foreground" />
            {__('Greeting Bubble', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Show a speech bubble above the button to greet visitors', 'wp-sms')}</CardDescription>
          <CardAction>
            <Switch
              checked={greeting_bubble.enabled}
              onCheckedChange={(checked) => onUpdate('greeting_bubble.enabled', checked)}
              aria-label="Toggle greeting bubble"
            />
          </CardAction>
        </CardHeader>
        {greeting_bubble.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel htmlFor="mb-greeting-message">Message</FieldLabel>
                <Input
                  id="mb-greeting-message"
                  value={greeting_bubble.message}
                  onChange={(e) => onUpdate('greeting_bubble.message', e.target.value)}
                  placeholder={__('Need help? We\'re online!', 'wp-sms')}
                />
              </Field>

              <div className="grid grid-cols-2 gap-4">
                <Field>
                  <FieldLabel htmlFor="mb-greeting-delay">Show After (seconds)</FieldLabel>
                  <Input
                    id="mb-greeting-delay"
                    type="number"
                    min={0}
                    value={greeting_bubble.delay}
                    onChange={(e) => onUpdate('greeting_bubble.delay', Number(e.target.value))}
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="mb-greeting-duration">Auto-dismiss (seconds)</FieldLabel>
                  <Input
                    id="mb-greeting-duration"
                    type="number"
                    min={0}
                    value={greeting_bubble.duration}
                    onChange={(e) => onUpdate('greeting_bubble.duration', Number(e.target.value))}
                  />
                  <FieldDescription>0 = stay until clicked</FieldDescription>
                </Field>
              </div>

              <Field orientation="horizontal">
                <FieldLabel>Open widget on click</FieldLabel>
                <Switch
                  checked={greeting_bubble.open_on_click}
                  onCheckedChange={(checked) => onUpdate('greeting_bubble.open_on_click', checked)}
                />
              </Field>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );
}
