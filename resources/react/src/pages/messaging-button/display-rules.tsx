import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { Eye, Clock, Plus, Trash2, AlertTriangle } from 'lucide-react';
import type { MessagingButtonSettings } from './use-mb-settings';

interface DisplayRulesPageProps {
  settings: MessagingButtonSettings;
  wpTimezone?: string;
  onUpdate: (key: string, value: unknown) => void;
}

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export function DisplayRulesPage({ settings, wpTimezone, onUpdate }: DisplayRulesPageProps) {
  const { display_rules, business_hours } = settings;
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
      {/* Display Rules */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Eye className="h-4 w-4 text-muted-foreground" />
            Display Rules
          </CardTitle>
          <CardDescription>Control where and to whom the widget appears</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Field orientation="horizontal">
              <FieldLabel>Auto-inject on all pages</FieldLabel>
              <Switch
                checked={display_rules.auto_inject}
                onCheckedChange={(checked) => onUpdate('display_rules.auto_inject', checked)}
              />
              <FieldDescription>
                When enabled, the widget appears on all frontend pages (unless excluded)
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel>Visibility</FieldLabel>
              <Select
                value={display_rules.visibility}
                onValueChange={(v) => onUpdate('display_rules.visibility', v)}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="everyone">Everyone</SelectItem>
                  <SelectItem value="logged_in">Logged In Users Only</SelectItem>
                  <SelectItem value="logged_out">Logged Out Visitors Only</SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel>Include URLs</FieldLabel>
              <Textarea
                value={display_rules.include_urls.join('\n')}
                onChange={(e) => {
                  const urls = e.target.value.split('\n').filter(Boolean);
                  onUpdate('display_rules.include_urls', urls);
                }}
                placeholder={'/pricing\n/contact\n/products/*'}
                rows={3}
              />
              <FieldDescription>
                Only show on these URLs (one per line). Supports wildcards (*). Leave empty to show on all pages.
              </FieldDescription>
            </Field>

            <Field>
              <FieldLabel>Exclude URLs</FieldLabel>
              <Textarea
                value={display_rules.exclude_urls.join('\n')}
                onChange={(e) => {
                  const urls = e.target.value.split('\n').filter(Boolean);
                  onUpdate('display_rules.exclude_urls', urls);
                }}
                placeholder={'/checkout\n/cart\n/wp-admin/*'}
                rows={3}
              />
              <FieldDescription>
                Hide on these URLs (one per line). Supports wildcards (*).
              </FieldDescription>
            </Field>
          </div>
        </CardContent>
      </Card>

      {/* Business Hours */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2 text-base">
              <Clock className="h-4 w-4 text-muted-foreground" />
              Business Hours
            </CardTitle>
            <Switch
              checked={business_hours.enabled}
              onCheckedChange={(checked) => onUpdate('business_hours.enabled', checked)}
            />
          </div>
          <CardDescription>
            Show different messaging when you're offline.
            {wpTimezone && <> Hours are evaluated in your WordPress timezone ({wpTimezone}).</>}
          </CardDescription>
        </CardHeader>
        {business_hours.enabled && (
          <CardContent>
            <div className="space-y-4">
              <Field>
                <FieldLabel>Offline Message</FieldLabel>
                <Input
                  value={business_hours.offline_message}
                  onChange={(e) => onUpdate('business_hours.offline_message', e.target.value)}
                  placeholder="We are currently offline."
                />
              </Field>

              <div className="space-y-2">
                <span className="text-sm font-medium">Schedule</span>
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
                        <span className="text-sm text-muted-foreground">to</span>
                        <Input
                          type="time"
                          value={entry.close}
                          onChange={(e) => updateSchedule(i, 'close', e.target.value)}
                          className="w-28"
                        />
                        <Button variant="ghost" size="icon" onClick={() => removeScheduleDay(i)}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                      {invalidTime && (
                        <p className="mt-1 ml-1 flex items-center gap-1 text-xs text-amber-600">
                          <AlertTriangle className="h-3 w-3" />
                          Open time should be before close time
                        </p>
                      )}
                    </div>
                  );
                })}
                {business_hours.schedule.length < 7 && (
                  <Button variant="outline" size="sm" onClick={addScheduleDay}>
                    <Plus className="mr-1 h-3 w-3" /> Add Day
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );
}
