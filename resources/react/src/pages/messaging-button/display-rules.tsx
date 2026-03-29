import { __, sprintf } from '@wordpress/i18n';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription, SwitchField } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { Eye, Clock, Plus, Trash2, AlertTriangle, Zap } from 'lucide-react';
import type { MessagingButtonSettings } from './use-mb-settings';

interface DisplayRulesPageProps {
  settings: MessagingButtonSettings;
  wpTimezone?: string;
  onUpdate: (key: string, value: unknown) => void;
}

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const SCHEDULE_MON_FRI = DAYS.slice(0, 5).map((day) => ({ day, open: '09:00', close: '17:00' }));
const SCHEDULE_MON_SAT = DAYS.slice(0, 6).map((day) => ({ day, open: '09:00', close: '18:00' }));
const SCHEDULE_EVERY_DAY = DAYS.map((day) => ({ day, open: '09:00', close: '17:00' }));

export function DisplayRulesPage({ settings, wpTimezone, onUpdate }: DisplayRulesPageProps) {
  const { display_rules, business_hours, triggers } = settings;
  const usedDays = business_hours.schedule.map((s) => s.day);

  const addScheduleDay = () => {
    const nextDay = DAYS.find((d) => !usedDays.includes(d));
    if (nextDay) {
      onUpdate('business_hours.schedule', [
        ...business_hours.schedule,
        { day: nextDay, open: '09:00', close: '17:00' },
      ]);
    }
  };

  const removeScheduleDay = (index: number) => {
    onUpdate('business_hours.schedule', business_hours.schedule.filter((_, i) => i !== index));
  };

  const updateSchedule = (index: number, field: string, value: string) => {
    const schedule = business_hours.schedule.map((s, i) =>
      i === index ? { ...s, [field]: value } : s
    );
    onUpdate('business_hours.schedule', schedule);
  };

  return (
    <div className="space-y-4">
      {/* Auto-Open Triggers */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Zap className="h-4 w-4 text-muted-foreground" />
            {__('Auto-Open Triggers', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Automatically open the widget based on visitor behavior. Each trigger has a 24-hour cooldown.', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Field>
              <FieldLabel htmlFor="mb-auto-open-delay">{__('Auto-open delay (seconds)', 'wp-sms')}</FieldLabel>
              <Input
                id="mb-auto-open-delay"
                type="number"
                min={0}
                value={triggers.auto_open_delay}
                onChange={(e) => onUpdate('triggers.auto_open_delay', Number(e.target.value))}
              />
              <FieldDescription>{__('Open the widget after this many seconds. 0 to disable.', 'wp-sms')}</FieldDescription>
            </Field>

            <Field>
              <FieldLabel htmlFor="mb-scroll-percent">{__('Scroll percentage', 'wp-sms')}</FieldLabel>
              <Input
                id="mb-scroll-percent"
                type="number"
                min={0}
                max={100}
                value={triggers.scroll_percent}
                onChange={(e) => onUpdate('triggers.scroll_percent', Number(e.target.value))}
              />
              <FieldDescription>{__('Open when visitor scrolls past this percentage. 0 to disable.', 'wp-sms')}</FieldDescription>
            </Field>

            <SwitchField
              id="exit-intent"
              label={__('Exit intent', 'wp-sms')}
              description={__('Open when the cursor moves toward closing the tab (desktop only)', 'wp-sms')}
              checked={triggers.exit_intent}
              onCheckedChange={(checked) => onUpdate('triggers.exit_intent', checked)}
            />
          </div>
        </CardContent>
      </Card>

      {/* Display Rules */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Eye className="h-4 w-4 text-muted-foreground" />
            {__('Display Rules', 'wp-sms')}
          </CardTitle>
          <CardDescription>{__('Control where and to whom the widget appears', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <SwitchField
              id="auto-inject"
              label={__('Auto-inject on all pages', 'wp-sms')}
              description={__('When enabled, the widget appears on all frontend pages (unless excluded)', 'wp-sms')}
              checked={display_rules.auto_inject}
              onCheckedChange={(checked) => onUpdate('display_rules.auto_inject', checked)}
            />

            <Field>
              <FieldLabel>{__('Visibility', 'wp-sms')}</FieldLabel>
              <Select
                value={display_rules.visibility}
                onValueChange={(v) => onUpdate('display_rules.visibility', v)}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="everyone">{__('Everyone', 'wp-sms')}</SelectItem>
                  <SelectItem value="logged_in">{__('Logged In Users Only', 'wp-sms')}</SelectItem>
                  <SelectItem value="logged_out">{__('Logged Out Visitors Only', 'wp-sms')}</SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel>{__('Include URLs', 'wp-sms')}</FieldLabel>
              <Textarea
                dir="ltr"
                value={display_rules.include_urls.join('\n')}
                onChange={(e) => {
                  const urls = e.target.value.split('\n').filter(Boolean);
                  onUpdate('display_rules.include_urls', urls);
                }}
                placeholder={'/pricing\n/contact\n/products/*'}
                rows={3}
              />
              <FieldDescription>
                {__('Only show on these URLs (one per line). Supports wildcards (*). Leave empty to show on all pages.', 'wp-sms')}
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel>{__('Exclude URLs', 'wp-sms')}</FieldLabel>
              <Textarea
                dir="ltr"
                value={display_rules.exclude_urls.join('\n')}
                onChange={(e) => {
                  const urls = e.target.value.split('\n').filter(Boolean);
                  onUpdate('display_rules.exclude_urls', urls);
                }}
                placeholder={'/checkout\n/cart\n/wp-admin/*'}
                rows={3}
              />
              <FieldDescription>
                {__('Hide on these URLs (one per line). Supports wildcards (*).', 'wp-sms')}
              </FieldDescription>
            </Field>
          </div>
        </CardContent>
      </Card>

      {/* Business Hours */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Clock className="h-4 w-4 text-muted-foreground" />
            {__('Business Hours', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Show different messaging when you\'re offline.', 'wp-sms')}
            {wpTimezone && <> {sprintf(__('Hours are evaluated in your WordPress timezone (%s).', 'wp-sms'), wpTimezone)}</>}
          </CardDescription>
          <CardAction>
            <Switch
              checked={business_hours.enabled}
              onCheckedChange={(checked) => onUpdate('business_hours.enabled', checked)}
              aria-label={__('Toggle business hours', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
        {business_hours.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel>{__('Offline Message', 'wp-sms')}</FieldLabel>
                <Input
                  value={business_hours.offline_message}
                  onChange={(e) => onUpdate('business_hours.offline_message', e.target.value)}
                  placeholder={__('We are currently offline.', 'wp-sms')}
                />
              </Field>

              <Field>
                <FieldLabel>{__('Schedule', 'wp-sms')}</FieldLabel>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" className="text-xs" onClick={() => onUpdate('business_hours.schedule', SCHEDULE_MON_FRI)}>
                    {__('Mon-Fri 9-5', 'wp-sms')}
                  </Button>
                  <Button variant="outline" size="sm" className="text-xs" onClick={() => onUpdate('business_hours.schedule', SCHEDULE_MON_SAT)}>
                    {__('Mon-Sat 9-6', 'wp-sms')}
                  </Button>
                  <Button variant="outline" size="sm" className="text-xs" onClick={() => onUpdate('business_hours.schedule', SCHEDULE_EVERY_DAY)}>
                    {__('Every Day', 'wp-sms')}
                  </Button>
                </div>
                {business_hours.schedule.map((entry, i) => {
                  const invalidTime = entry.open && entry.close && entry.open >= entry.close;
                  return (
                    <div key={i}>
                      <div className="flex items-center gap-2">
                        <Select value={entry.day} onValueChange={(v) => updateSchedule(i, 'day', v)}>
                          <SelectTrigger className="w-36">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {DAYS.filter((d) => d === entry.day || !usedDays.includes(d)).map((d) => (
                              <SelectItem key={d} value={d} className="capitalize">{d}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <Input
                          type="time"
                          value={entry.open}
                          onChange={(e) => updateSchedule(i, 'open', e.target.value)}
                          className="w-28"
                        />
                        <span className="text-sm text-muted-foreground">{__('to', 'wp-sms')}</span>
                        <Input
                          type="time"
                          value={entry.close}
                          onChange={(e) => updateSchedule(i, 'close', e.target.value)}
                          className="w-28"
                        />
                        <Button variant="ghost" size="icon" onClick={() => removeScheduleDay(i)} aria-label={__('Remove day', 'wp-sms')}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                      {invalidTime && (
                        <p className="mt-1 ms-1 flex items-center gap-1 text-xs text-amber-600">
                          <AlertTriangle className="h-3 w-3" />
                          {__('Open time should be before close time', 'wp-sms')}
                        </p>
                      )}
                    </div>
                  );
                })}
                {business_hours.schedule.length < 7 && (
                  <Button variant="outline" size="sm" onClick={addScheduleDay}>
                    <Plus className="me-1 h-3 w-3" /> {__('Add Day', 'wp-sms')}
                  </Button>
                )}
              </Field>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );
}
