import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Paintbrush, MousePointerClick, Sun, Moon, Monitor } from 'lucide-react';
import type { MessagingButtonSettings } from './use-mb-settings';

interface AppearancePageProps {
  settings: MessagingButtonSettings;
  onUpdate: (key: string, value: unknown) => void;
}

export function AppearancePage({ settings, onUpdate }: AppearancePageProps) {
  const { button, widget, enabled } = settings;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <MousePointerClick className="h-4 w-4 text-muted-foreground" />
            Enable Widget
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Field orientation="horizontal">
            <FieldLabel htmlFor="mb-enabled">Show messaging button on your site</FieldLabel>
            <Switch
              id="mb-enabled"
              checked={enabled}
              onCheckedChange={(checked) => onUpdate('enabled', checked)}
            />
          </Field>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Paintbrush className="h-4 w-4 text-muted-foreground" />
            Button Appearance
          </CardTitle>
          <CardDescription>Customize the floating button style and position</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <Field>
                <FieldLabel>Position</FieldLabel>
                <Select value={button.position} onValueChange={(v) => onUpdate('button.position', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="bottom-right">Bottom Right</SelectItem>
                    <SelectItem value="bottom-left">Bottom Left</SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel>Style</FieldLabel>
                <Select value={button.style} onValueChange={(v) => onUpdate('button.style', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="icon-text">Icon + Text</SelectItem>
                    <SelectItem value="icon">Icon Only</SelectItem>
                    <SelectItem value="text">Text Only</SelectItem>
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
                placeholder="Chat with us"
              />
              <FieldDescription>Displayed when style includes text</FieldDescription>
            </Field>

            <div className="grid grid-cols-2 gap-4">
              <Field>
                <FieldLabel htmlFor="mb-primary-color">Primary Color</FieldLabel>
                <div className="flex gap-2">
                  <input
                    type="color"
                    value={button.primary_color}
                    onChange={(e) => onUpdate('button.primary_color', e.target.value)}
                    className="h-9 w-12 cursor-pointer rounded border border-input p-1"
                  />
                  <Input
                    id="mb-primary-color"
                    value={button.primary_color}
                    onChange={(e) => onUpdate('button.primary_color', e.target.value)}
                    placeholder="#2563eb"
                    className="font-mono"
                  />
                </div>
              </Field>

              <Field>
                <FieldLabel htmlFor="mb-text-color">Text Color</FieldLabel>
                <div className="flex gap-2">
                  <input
                    type="color"
                    value={button.text_color}
                    onChange={(e) => onUpdate('button.text_color', e.target.value)}
                    className="h-9 w-12 cursor-pointer rounded border border-input p-1"
                  />
                  <Input
                    id="mb-text-color"
                    value={button.text_color}
                    onChange={(e) => onUpdate('button.text_color', e.target.value)}
                    placeholder="#ffffff"
                    className="font-mono"
                  />
                </div>
              </Field>
            </div>

            <Field>
              <FieldLabel>Attention Effect</FieldLabel>
              <Select value={button.attention} onValueChange={(v) => onUpdate('button.attention', v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  <SelectItem value="pulse">Pulse</SelectItem>
                  <SelectItem value="bounce">Bounce</SelectItem>
                  <SelectItem value="badge">Badge</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Widget Header</CardTitle>
          <CardDescription>Set the greeting shown when the widget opens</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Field>
              <FieldLabel htmlFor="mb-widget-title">Title</FieldLabel>
              <Input
                id="mb-widget-title"
                value={widget.title}
                onChange={(e) => onUpdate('widget.title', e.target.value)}
                placeholder="Hi there!"
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="mb-widget-subtitle">Subtitle</FieldLabel>
              <Input
                id="mb-widget-subtitle"
                value={widget.subtitle}
                onChange={(e) => onUpdate('widget.subtitle', e.target.value)}
                placeholder="How can we help?"
              />
            </Field>

            <Field>
              <FieldLabel>Theme</FieldLabel>
              <RadioGroup
                value={widget.theme}
                onValueChange={(v) => onUpdate('widget.theme', v)}
                className="flex gap-3"
              >
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="light" id="theme-light" />
                  <Label htmlFor="theme-light" className="flex items-center gap-1.5 text-sm font-normal cursor-pointer">
                    <Sun className="h-3.5 w-3.5" /> Light
                  </Label>
                </div>
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="dark" id="theme-dark" />
                  <Label htmlFor="theme-dark" className="flex items-center gap-1.5 text-sm font-normal cursor-pointer">
                    <Moon className="h-3.5 w-3.5" /> Dark
                  </Label>
                </div>
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="system" id="theme-system" />
                  <Label htmlFor="theme-system" className="flex items-center gap-1.5 text-sm font-normal cursor-pointer">
                    <Monitor className="h-3.5 w-3.5" /> System
                  </Label>
                </div>
              </RadioGroup>
              <FieldDescription>Controls the widget panel color scheme</FieldDescription>
            </Field>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
