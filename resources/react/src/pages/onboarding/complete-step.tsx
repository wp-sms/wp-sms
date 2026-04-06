import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { CheckCircle2, ArrowRight, LogIn, Workflow, Users, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { UseGatewaysReturn } from '@/hooks/use-gateways';
import { toast } from 'sonner';
import type { OnboardingGoal } from '@/lib/api';

const GOAL_LABELS: Record<string, string> = {
  auth: __('Auth & Identity', 'wp-sms'),
  notifications: __('SMS & Notifications', 'wp-sms'),
  campaigns: __('Marketing & Campaigns', 'wp-sms'),
};

interface CompleteStepProps {
  goals: OnboardingGoal[];
  authEnabled: boolean;
  sitePhone: string;
  onFinish: () => void;
  gatewaysHook: UseGatewaysReturn;
}

export function CompleteStep({ goals, authEnabled, sitePhone, onFinish, gatewaysHook }: CompleteStepProps) {
  const { gateways, testGateway } = gatewaysHook;
  const configured = gateways.filter((g) => g.is_configured);
  const hasGateway = configured.length > 0;

  const [testTo, setTestTo] = useState(sitePhone);
  const [testSending, setTestSending] = useState(false);

  const handleTestSend = async () => {
    if (!testTo.trim() || configured.length === 0) return;
    setTestSending(true);
    try {
      const gw = configured[0];
      const smsChannel = gw.supported_channels.includes('sms') ? 'sms' : gw.supported_channels[0];
      await testGateway(gw.id, { channel: smsChannel, to: testTo.trim(), body: __('Hello from WSMS! Your gateway is working.', 'wp-sms') });
      toast.success(__('Test message sent!', 'wp-sms'));
    } catch {
      toast.error(__('Failed to send test message.', 'wp-sms'));
    } finally {
      setTestSending(false);
    }
  };

  const nextSteps = goals.map((goal) => {
    switch (goal) {
      case 'auth':
        return { icon: LogIn, title: __('Customize auth channels', 'wp-sms'), route: 'identity' };
      case 'notifications':
        return { icon: Workflow, title: __('Create an automation', 'wp-sms'), route: 'automation' };
      case 'campaigns':
        return { icon: Users, title: __('Import contacts', 'wp-sms'), route: 'audience' };
      default:
        return null;
    }
  }).filter(Boolean) as Array<{ icon: typeof LogIn; title: string; route: string }>;

  return (
    <div className="space-y-6">
      {/* Success hero */}
      <div className="text-center py-4">
        <div className="inline-flex h-16 w-16 items-center justify-center rounded-full border-2 border-green-600 bg-green-50 dark:bg-green-900/20 mb-4 animate-fade-up">
          <CheckCircle2 className="h-7 w-7 text-green-600" />
        </div>
        <h1 className="text-xl font-extrabold tracking-tight mb-1">
          {__('You\'re all set!', 'wp-sms')}
        </h1>
        <p className="text-sm text-muted-foreground">
          {__('WSMS is configured and ready to go.', 'wp-sms')}
        </p>
      </div>

      {/* Summary */}
      <div className="border-2 rounded-md bg-card overflow-hidden">
        {[
          { label: __('Goals', 'wp-sms'), value: goals.map((g) => GOAL_LABELS[g] ?? g).join(', ') },
          { label: __('Gateway', 'wp-sms'), value: hasGateway ? configured[0].name : __('Not configured', 'wp-sms'), badge: true, variant: hasGateway ? 'default' : 'outline' },
          { label: __('Authentication', 'wp-sms'), value: authEnabled ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'), badge: true, variant: authEnabled ? 'default' : 'outline' },
          ...(sitePhone ? [{ label: __('Phone', 'wp-sms'), value: sitePhone }] : []),
        ].map((row, i) => (
          <div key={i} className={`flex items-center justify-between px-5 py-3 text-sm ${i > 0 ? 'border-t border-border/40' : ''}`}>
            <span className="text-muted-foreground">{row.label}</span>
            {'badge' in row && row.badge ? (
              <Badge variant={row.variant as 'default' | 'outline'}>{row.value}</Badge>
            ) : (
              <span className="font-semibold">{row.value}</span>
            )}
          </div>
        ))}
      </div>

      {/* Test message */}
      {hasGateway && (
        <div className="border-2 rounded-md bg-accent/30 p-5">
          <div className="text-sm font-semibold mb-0.5">{__('Send a test message', 'wp-sms')}</div>
          <p className="text-sm text-muted-foreground mb-3">{__('Verify your gateway is working.', 'wp-sms')}</p>
          <div className="flex gap-2 max-w-sm">
            <Input
              type="tel"
              dir="ltr"
              value={testTo}
              onChange={(e) => setTestTo(e.target.value)}
              placeholder="+1234567890"
              className="flex-1"
            />
            <Button onClick={handleTestSend} disabled={testSending || !testTo.trim()} size="sm">
              {testSending && <Loader2 className="size-3.5 me-1 animate-spin" />}
              {__('Send', 'wp-sms')}
            </Button>
          </div>
        </div>
      )}

      {/* Next steps */}
      {nextSteps.length > 0 && (
        <div>
          <h3 className="text-sm font-semibold mb-2.5">{__('What to do next', 'wp-sms')}</h3>
          <div className="grid gap-2.5 grid-cols-3">
            {nextSteps.map((step) => {
              const Icon = step.icon;
              return (
                <button
                  key={step.route}
                  type="button"
                  onClick={onFinish}
                  className="group p-4 text-center bg-card border-2 rounded-md cursor-pointer transition-colors hover:border-primary"
                >
                  <Icon className="size-6 text-muted-foreground mx-auto mb-2 transition-colors group-hover:text-primary" />
                  <span className="text-sm font-semibold">{step.title}</span>
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* Finish */}
      <div className="flex justify-center pt-2">
        <Button onClick={onFinish}>
          {__('Go to Dashboard', 'wp-sms')}
          <ArrowRight className="ms-1 size-4 rtl:scale-x-[-1]" />
        </Button>
      </div>
    </div>
  );
}
